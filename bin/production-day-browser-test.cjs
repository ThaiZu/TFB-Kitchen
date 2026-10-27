const {chromium}=require('playwright');
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const fixtureDir=process.env.KITCHEN_DAY_FIXTURE_DIR||path.join(require('node:os').tmpdir(),'kitchen-day-mvp');
const kitchen=path.resolve(__dirname,'..'),fixture=fs.readFileSync(path.join(fixtureDir,process.env.KITCHEN_DAY_REAL_SHELL==='1'?'kitchen-runtime-day.html':'ui.html'),'utf8');
const initial=JSON.parse(fixture.match(/<script id="pd-seed"[^>]*>([\s\S]*?)<\/script>/)[1]);
(async()=>{const browser=await chromium.launch({headless:true,executablePath:process.env.CHROMIUM_PATH||'/usr/bin/chromium',args:['--no-sandbox']});let checks=0;try{
for(const width of [1440,768,390]){
 const page=await browser.newPage({viewport:{width,height:1000},isMobile:width<1000,hasTouch:width<1000,deviceScaleFactor:width<1000?2:1}),errors=[],writes=[],responses=new Map();let sheet=structuredClone(initial),loseClosing=true,applied=0;
 page.on('pageerror',e=>errors.push(e.message));
 await page.route('**/*',async route=>{const url=new URL(route.request().url()),p=url.pathname;
  if(p.startsWith('/kitchen/assets/')){const f=path.join(kitchen,'public',p.slice('/kitchen/'.length));return route.fulfill({contentType:p.endsWith('.js')?'application/javascript':'text/css',body:fs.existsSync(f)?fs.readFileSync(f):''});}
  if(p.startsWith('/kitchen/ajax/'))return route.fulfill({json:{success:true,total:0,count:0}});
  if(p==='/kitchen/production-day/data')return route.fulfill({json:{success:true,data:sheet}});
  if(route.request().method()==='POST'){
   const input=route.request().postDataJSON(),op=p.split('/').pop();writes.push({op,input});
   if(responses.has(input.request_id))return route.fulfill({json:responses.get(input.request_id)});
   assert.equal(input.expected_revision,sheet.revision,'UI sends current revision');
   const snapshot={lines:[]};
   for(const line of input.lines){const product=sheet.products.find(p=>p.id_product===line.id_product),before=Number(product.stock_quantity),n=Number(line.quantity);assert.equal(Number(line.expected_stock),before);
    let after;if(op==='open'){after=n;snapshot.lines.push({id_product:product.id_product,name:product.name,previous_quantity:String(before),opening_quantity:String(n),produced_quantity:String(Math.max(0,n-before)),waste_quantity:String(Math.max(0,before-n))});}
    else if(op==='adjust')after=before+(line.mode==='produce'?n:-n);
    else{after=before-n;snapshot.lines.push({id_product:product.id_product,name:product.name,shelf_life_category:product.shelf_life_category,waste_quantity:String(n),carryover_quantity:String(after)});if(product.shelf_life_category==='SSL')assert.equal(n,before);}
    product.stock_quantity=product.in_stock=after.toFixed(4);product.last_movement_id=String(Number(product.last_movement_id)+1);
   }
   applied++;sheet.revision++;sheet.status=op==='close'?'CLOSED':'OPEN';if(op==='open')sheet.opening=snapshot;if(op==='close'){sheet.closing=snapshot;sheet.read_only=true;}
   const result={success:true,data:{date:sheet.date,status:sheet.status,revision:sheet.revision,replayed:false}};responses.set(input.request_id,result);
   if(op==='close'&&loseClosing){loseClosing=false;return route.abort('failed');}return route.fulfill({json:result});
  }
  return route.fulfill({contentType:'text/html',body:fixture.replace(/(<script id="pd-seed"[^>]*>)[\s\S]*?(<\/script>)/,(_,a,b)=>a+JSON.stringify(sheet).replace(/</g,'\\u003c')+b)});
 });
 async function chooseProduct(name){if(!await page.locator('.pd-add').isVisible())await page.locator('#pd-add-toggle').click();await page.locator('.pd-add .choices__inner').click();await page.locator('.pd-add .choices__input--cloned').fill(name);await page.locator('.pd-add .choices__list--dropdown [data-choice-selectable]').filter({hasText:name}).click();}
 await page.goto('http://kitchen.test/kitchen/production-day');
 await page.waitForSelector('[data-opening-product="1"]');
 assert.equal(await page.locator('[data-opening-product]').count(),4);checks++;
 const toolbar=await Promise.all(['#pd-status','.pd-date-form','#pd-refresh'].map(selector=>page.locator(selector).boundingBox()));assert.ok(Math.max(...toolbar.map(b=>b.y+b.height/2))-Math.min(...toolbar.map(b=>b.y+b.height/2))<2,'Status/date/refresh share one row');
 assert.equal(await page.locator('.pd-scope,.pd-meta').count(),0);
 if(await page.locator('.app-tabbar-bar').count())assert.equal(await page.locator('.pd-bottom-dock>.app-tabbar-bar').count(),1,'Footer and navigation share a single dock');
 await page.setViewportSize({width,height:700});await page.waitForTimeout(100);const compact=await page.locator('#pd-footer').boundingBox();assert.ok(compact.y>=0&&compact.y+compact.height<=701,'Footer remains visible when mobile viewport shrinks');await page.setViewportSize({width,height:1000});

 await page.evaluate(()=>{const rows=document.querySelector('.pd-table tbody');for(let i=0;i<35;i++){const row=document.createElement('tr');row.className='test-scroll-row';row.innerHTML='<td>Scroll fixture</td><td>1</td><td>1</td>';rows.append(row);}window.scrollTo(0,document.body.scrollHeight/2);});
 await page.waitForTimeout(100);const sticky=await page.locator('#pd-footer').boundingBox();assert.ok(sticky.y>=0&&sticky.y+sticky.height<=1001,'Footer stays in viewport while scrolling');
 if(await page.locator('.app-tabbar-bar').count()){const nav=await page.locator('.app-tabbar-bar').boundingBox();assert.ok(sticky.y+sticky.height<=nav.y+1,'Footer stays above the real bottom navbar');}
 const buttons=page.locator('.pd-footer-actions button');assert.equal(await buttons.count(),2);for(const b of await buttons.all()){assert.ok(await b.evaluate(n=>{const r=n.getBoundingClientRect();return n.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2));}),'Footer button can be hit without another element covering it');}
 assert.equal(await page.locator('#pd-footer').getByRole('button',{name:'Otwórz dzień',exact:true}).count(),1);
 assert.equal(await page.locator('.pd-add').isVisible(),false);await page.locator('#pd-add-toggle').click();await page.locator('.pd-add .choices__inner').click();await page.locator('.pd-add .choices__input--cloned').fill('Late');const menu=await page.locator('.pd-add .choices__list--dropdown').boundingBox();assert.ok(menu.y>=0&&menu.y+menu.height<=sticky.y+sticky.height,'Search opens above the footer');await page.keyboard.press('Escape');
 await page.evaluate(()=>window.scrollTo(0,document.body.scrollHeight));await page.waitForTimeout(100);const lastRow=await page.locator('.test-scroll-row').last().boundingBox(),bottomPanel=await page.locator('#pd-footer').boundingBox();assert.ok(lastRow.y+lastRow.height<=bottomPanel.y,'Footer does not cover the final table row');
 await page.evaluate(()=>{document.querySelectorAll('.test-scroll-row').forEach(n=>n.remove());window.scrollTo(0,0);});

 const cell=page.locator('#pd-body td').filter({has:page.locator('.pd-product-name').filter({hasText:'Mandatory cake'})});const nameRect=await cell.locator('.pd-product-name').boundingBox(),badgesRect=await cell.locator('.pd-product-badges').boundingBox();assert.ok(badgesRect.y>=nameRect.y+nameRect.height,'Opening badges appear below product name');
 assert.equal(await cell.locator('.pd-badge').allTextContents().then(x=>x.join(',')),'MSL');assert.equal(await cell.locator('.pd-mandatory-lock').getAttribute('aria-label'),'Obowiązkowy');
 assert.equal(await cell.locator('.pd-sector,.pd-unit').count(),0);assert.equal(await cell.locator('.pd-product-name').innerText(),'🔒 Mandatory cake');
 const weightedRow=page.locator('tr').filter({has:page.locator('[data-opening-product="6"]')});assert.match(await weightedRow.locator('td').nth(1).innerText(),/1\.25\s+kg/);assert.equal(await weightedRow.locator('.pd-mandatory-lock').count(),0);

 assert.equal(await page.locator('[data-opening-product="2"]').inputValue(),'0');
 assert.equal(await page.locator('[data-page="stock"]').isDisabled(),true);
 await page.locator('[data-opening-product="1"]').locator('..').getByRole('button',{name:'Zwiększ o 1 szt.',exact:true}).click();assert.equal(await page.locator('[data-opening-product="1"]').inputValue(),'11');await page.locator('[data-opening-product="1"]').locator('..').getByRole('button',{name:'Zmniejsz o 1 szt.',exact:true}).click();
 await page.locator('[data-opening-product="6"]').locator('..').getByRole('button',{name:'Zwiększ o 0,1 kg',exact:true}).click();assert.equal(await page.locator('[data-opening-product="6"]').inputValue(),'1.35');await page.locator('[data-opening-product="6"]').fill('1.5');
 await chooseProduct('Extra bun');await page.locator('.pd-add input[type=number]').fill('6');await page.locator('.pd-add button').click();assert.equal(await page.locator('.pd-add').isVisible(),false);
 assert.equal(await page.locator('[data-opening-product]').last().getAttribute('data-opening-product'),'4');checks++;
 const selectAll=page.locator('[data-opening-product="2"]');await selectAll.click();await selectAll.pressSequentially('154');assert.equal(await selectAll.inputValue(),'154','Click selects the whole editable quantity');await selectAll.fill('5');await page.locator('[data-opening-product="1"]').fill('8');await page.locator('[data-opening-product="3"]').fill('2');
 await page.getByRole('button',{name:'Otwórz dzień',exact:true}).click();await page.waitForSelector('dialog[open]');
 assert.equal(await page.locator('.pd-decision').count(),2);assert.equal(await page.locator('.pd-decision input[type="number"]').first().isEditable(),false);
 const first=page.locator('.pd-decision').filter({hasText:'Croissant'}),second=page.locator('.pd-decision').filter({hasText:'Long life juice'});
 await first.locator('[data-waste-reason="expiration"]').click();await first.getByRole('button',{name:'Potwierdź wyrzucenie',exact:true}).click();
 assert.equal(await first.locator('.pd-success').evaluate(n=>getComputedStyle(n).backgroundColor),'rgb(46, 125, 50)');assert.equal(await first.locator('.pd-restore').evaluate(n=>getComputedStyle(n).borderTopColor),'rgb(217, 163, 0)');assert.equal(await page.locator('#pd-dialog-save').evaluate(n=>getComputedStyle(n).backgroundColor),'rgb(46, 125, 50)');
 const confirmRect=await first.locator('.pd-shortage-confirmation>button').boundingBox(),confirmedRect=await first.locator('.pd-confirmed-label').boundingBox();assert.ok(confirmRect.x+confirmRect.width<=confirmedRect.x&&Math.abs(confirmRect.y+confirmRect.height/2-confirmedRect.y-confirmedRect.height/2)<2,'Confirmation button and checkbox label share one row');
 await first.getByRole('button',{name:'Pozostaw produkty — przywróć stan',exact:true}).click();assert.equal(await first.count(),0,'Restoring a previously confirmed product removes its shortage card');assert.equal(await page.locator('[data-opening-product="1"]').inputValue(),'10');
 assert.equal(await second.locator('select').count(),0,'Opening reasons are badges, not dropdowns');
 await second.locator('[data-waste-reason="other_waste"]').click();assert.equal(await second.getByRole('button',{name:'Potwierdź wyrzucenie',exact:true}).isDisabled(),true,'Other reason requires a comment');await second.locator('.pd-allocation-row').filter({has:page.locator('[data-waste-reason="other_waste"]')}).locator('textarea').fill('Test explanation');assert.equal(await second.getByRole('button',{name:'Potwierdź wyrzucenie',exact:true}).isEnabled(),true);await second.locator('[data-waste-reason="other_waste"]').click();
 await second.locator('[data-waste-reason="expiration"]').click();assert.equal(await second.locator('[data-waste-reason="tasting"]').isVisible(),false,'Full allocation hides unselected reasons');const reasonRow=second.locator('.pd-allocation-row').filter({has:page.locator('[data-waste-reason="expiration"]')});assert.equal(await reasonRow.locator('textarea').count(),0,'Ordinary reasons have no comment field');const badgeRect=await reasonRow.locator('button').boundingBox(),qtyRect=await reasonRow.locator('input').boundingBox();assert.ok(badgeRect.x+badgeRect.width<=qtyRect.x&&Math.abs(badgeRect.y+badgeRect.height/2-qtyRect.y-qtyRect.height/2)<2,'Reason and quantity share one row');await second.locator('[data-allocation-quantity="expiration"]').fill('1');assert.equal(await second.locator('[data-waste-reason="tasting"]').isVisible(),true,'Reducing allocation reveals remaining reasons');
 const confirmSplit=second.getByRole('button',{name:'Potwierdź wyrzucenie',exact:true});assert.equal(await confirmSplit.isDisabled(),true,'Under-allocation cannot be confirmed');
 await second.locator('[data-waste-reason="tasting"]').click();assert.equal(await second.locator('[data-allocation-quantity="tasting"]').inputValue(),'1');await confirmSplit.click();
 await second.locator('[data-allocation-quantity="tasting"]').fill('2');assert.equal(await second.locator('input[type=checkbox]').isChecked(),false,'Editing a confirmed split invalidates confirmation');assert.equal(await confirmSplit.isDisabled(),true,'Over-allocation cannot be confirmed');
 await second.locator('[data-allocation-quantity="tasting"]').fill('1');await confirmSplit.click();
 if(width===390)await page.screenshot({path:path.join(fixtureDir,'mobile-opening-allocations.png')});
 await page.locator('#pd-dialog-save').click();await page.waitForFunction(()=>document.getElementById('pd-status').textContent==='Dzień otwarty',{},{timeout:5000}).catch(async e=>{console.error(await page.evaluate(()=>({notice:document.getElementById('pd-notice').textContent,error:document.getElementById('pd-dialog-error').textContent,invalid:[...document.querySelectorAll(':invalid')].map(n=>[n.tagName,n.value,n.validationMessage]),status:document.getElementById('pd-status').textContent})),writes,errors);throw e;});
 const actionBox=await page.locator('#pd-body tbody tr:has(td)').first().getByRole('button').boundingBox();assert.ok(actionBox.x>=0&&actionBox.x+actionBox.width<=width,'Stock action visible without horizontal scrolling');
 if(width===390)await page.screenshot({path:path.join(fixtureDir,'mobile-stock.png'),fullPage:true});
 assert.equal(writes.length,1);assert.equal(writes[0].input.lines.find(l=>l.id_product===1).quantity,'10');assert.deepEqual(writes[0].input.lines.find(l=>l.id_product===3).waste_allocations,[{reason:'expiration',quantity:'1',comment:null},{reason:'tasting',quantity:'1',comment:null}]);checks+=2;
 assert.equal(await page.getByRole('button',{name:'Otwórz dzień',exact:true}).count(),0,'Open-day action disappears once the day is open');assert.equal(await page.locator('.pd-footer-actions button').count(),1);
 await chooseProduct('Late tart');await page.locator('.pd-add input[type=number]').fill('3');await page.locator('.pd-add button').click();await page.waitForSelector('dialog[open]');assert.equal(await page.locator('[data-adjust-mode="waste"]').count(),0,'New product only permits production');assert.equal(await page.locator('[data-adjust-mode="produce"]').count(),1);await page.locator('#pd-dialog-save').click();await page.waitForSelector('dialog[open]',{state:'hidden'});assert.equal(sheet.products.find(p=>p.id_product===5).stock_quantity,'3.0000');checks++;
 const cake=page.locator('#pd-body tbody tr').filter({hasText:'Mandatory cake'});
 await cake.getByRole('button').click();await page.locator('#pd-dialog-body input[type="number"]').fill('2');await page.locator('#pd-dialog-save').click();await page.waitForSelector('dialog[open]',{state:'hidden'});
 assert.equal(sheet.products.find(p=>p.id_product===2).stock_quantity,'7.0000');checks++;
 await cake.getByRole('button').click();await page.locator('[data-adjust-mode="waste"]').click();await page.locator('#pd-dialog-body input[type="number"]').fill('1');await page.locator('[data-adjust-reason="other_waste"]').click();assert.equal(await page.locator('.pd-stock-reasons textarea').isVisible(),true);await page.locator('[data-adjust-reason="damage"]').click();assert.equal(await page.locator('.pd-stock-reasons textarea').isVisible(),false);assert.equal(await page.locator('#pd-dialog-body select').count(),0,'Operations and reasons use badges');await page.locator('#pd-dialog-save').click();await page.waitForSelector('dialog[open]',{state:'hidden'});assert.equal(sheet.products.find(p=>p.id_product===2).stock_quantity,'6.0000');checks++;
 await page.locator('[data-page="closing"]').click();assert.equal(await page.locator('#pd-footer').getByRole('button',{name:'Zamknij dzień',exact:true}).count(),1);assert.equal(await page.locator('#pd-body').getByRole('button',{name:'Zamknij dzień',exact:true}).count(),0);assert.equal(await page.locator('[data-closing-product="1"]').inputValue(),'10');assert.equal(await page.locator('[data-closing-product="1"]').isEditable(),false);checks++;
 await page.getByRole('button',{name:'MSL · średnia trwałość',exact:true}).click();assert.equal(await page.locator('[data-closing-product="6"]').inputValue(),'0');await page.locator('[data-closing-product="6"]').fill('0.125');assert.equal(await page.locator('[data-closing-product="2"]').inputValue(),'0');await page.locator('[data-closing-product="2"]').fill('2');
 await page.getByRole('button',{name:'LSL · długa trwałość',exact:true}).click();assert.equal(await page.locator('[data-closing-product="3"]').inputValue(),'0');checks++;
 await page.getByRole('button',{name:'Zamknij dzień',exact:true}).click();await page.waitForSelector('dialog[open]');
 const disposal=page.locator('.pd-decision');assert.equal(await disposal.count(),4);
 assert.equal(await page.locator('#pd-dialog-body select').count(),0,'Closing reasons use badges instead of dropdowns');
 const closeRequests=writes.filter(w=>w.op==='close').length;await page.locator('#pd-dialog-save').click();assert.equal(writes.filter(w=>w.op==='close').length,closeRequests,'Closing requires explicit per-product confirmation');
 for(let i=0;i<await disposal.count();i++){const row=disposal.nth(i);if((await row.locator('strong').innerText()).includes('SSL'))assert.equal(await row.locator('[data-closing-reason="expiration"]').getAttribute('aria-pressed'),'true');else await row.locator('[data-closing-reason="quality"]').click();assert.equal(await row.locator('textarea').isVisible(),false,'Ordinary reasons do not show a comment');const confirmDisposal=row.locator('[data-confirm-disposal]');await confirmDisposal.click();assert.equal(await row.locator('input[type="checkbox"]').isChecked(),true,'Large button confirms the checkbox');const a=await confirmDisposal.boundingBox(),b=await row.locator('.pd-confirmed-label').boundingBox();assert.ok(a.x+a.width<=b.x&&Math.abs(a.y+a.height/2-b.y-b.height/2)<2,'Closing confirmation and checkbox share a row');}
 const editable=disposal.first();await editable.locator('[data-closing-reason="other_waste"]').click();assert.equal(await editable.locator('input[type="checkbox"]').isChecked(),false,'Changing reason invalidates confirmation');assert.equal(await editable.locator('textarea').isVisible(),true);assert.equal(await editable.locator('[data-confirm-disposal]').isDisabled(),true);await editable.locator('textarea').fill('Test closing explanation');await editable.locator('[data-confirm-disposal]').click();assert.equal(await editable.locator('input[type="checkbox"]').isChecked(),true);await editable.locator('textarea').fill('Updated closing explanation');assert.equal(await editable.locator('input[type="checkbox"]').isChecked(),false,'Changing comment invalidates confirmation');await editable.locator('[data-closing-reason="expiration"]').click();assert.equal(await editable.locator('textarea').isVisible(),false);await editable.locator('[data-confirm-disposal]').click();
 if(width===390)await page.screenshot({path:path.join(fixtureDir,'mobile-closing-confirmation.png')});
 await page.locator('#pd-dialog-save').click();await page.waitForSelector('#pd-notice button');
 const key=writes.at(-1).input.request_id,beforeRetry=applied;
 await page.reload();await page.waitForSelector('#pd-notice button');await page.locator('#pd-notice button').click();await page.waitForFunction(()=>document.getElementById('pd-notice').textContent==='Zapisano.');
 assert.equal(writes.at(-1).input.request_id,key);assert.equal(applied,beforeRetry);assert.equal(sheet.status,'CLOSED');assert.equal(sheet.products.find(p=>p.id_product===6).stock_quantity,'1.3750');assert.equal(sheet.products.find(p=>p.id_product===1).stock_quantity,'0.0000');assert.equal(sheet.products.find(p=>p.id_product===3).stock_quantity,'2.0000');checks+=2;
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,'No page overflow '+width);assert.deepEqual(errors,[]);checks++;
 // All shortages restored: no frozen card remains and no opening is submitted automatically.
 sheet=structuredClone(initial);await page.goto('http://kitchen.test/kitchen/production-day');await page.locator('[data-opening-product="1"]').fill('9');await page.getByRole('button',{name:'Otwórz dzień',exact:true}).click();await page.waitForSelector('dialog[open]');const beforeRestore=writes.length;await page.getByRole('button',{name:'Pozostaw produkty — przywróć stan',exact:true}).click();assert.equal(await page.locator('.pd-decision').count(),0);assert.equal(await page.locator('.pd-no-shortages').isVisible(),true);assert.equal(writes.length,beforeRestore);await page.locator('#pd-dialog-cancel').click();assert.equal(await page.locator('[data-opening-product="1"]').inputValue(),'10');checks++;
 await page.close();
}
// A pending past day offers closing with live stock, never historical production edits.
const late=structuredClone(initial);late.date='2020-01-01';late.status='OPEN';late.late_closing=true;late.default_view='closing';late.read_only=false;
const latePage=await browser.newPage({viewport:{width:390,height:1000}});
await latePage.route('**/*',route=>{const p=new URL(route.request().url()).pathname;if(p.startsWith('/kitchen/assets/')){const f=path.join(kitchen,'public',p.slice('/kitchen/'.length));return route.fulfill({contentType:p.endsWith('.js')?'application/javascript':'text/css',body:fs.existsSync(f)?fs.readFileSync(f):''});}return route.fulfill({contentType:'text/html',body:fixture.replace('data-view="opening"','data-view="closing"').replace(/(<script id="pd-seed"[^>]*>)[\s\S]*?(<\/script>)/,(_,a,b)=>a+JSON.stringify(late).replace(/</g,'\\u003c')+b)});});
await latePage.goto('http://kitchen.test/kitchen/production-day');
assert.equal(await latePage.locator('[data-page="closing"]').getAttribute('aria-current'),'page');
assert.equal(await latePage.locator('[data-closing-product="1"]').inputValue(),'10');
assert.equal(await latePage.getByRole('button',{name:'Zamknij dzień',exact:true}).isEnabled(),true);
await latePage.locator('[data-page="stock"]').click();assert.equal(await latePage.locator('.pd-add').count(),0);assert.equal(await latePage.getByRole('button',{name:'Zmodyfikuj stany bieżące',exact:true}).count(),0);
await latePage.close();checks++;
console.log(`Kitchen production day browser: ${checks} checks PASS (desktop/mobile, opening decisions, production, waste, closing, persisted retry).`);
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1);});
