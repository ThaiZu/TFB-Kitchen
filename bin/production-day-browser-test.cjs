const {chromium}=require('playwright');
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const fixtureDir=process.env.KITCHEN_DAY_FIXTURE_DIR||path.join(require('node:os').tmpdir(),'kitchen-day-mvp');
const kitchen=path.resolve(__dirname,'..'),fixture=fs.readFileSync(path.join(fixtureDir,'ui.html'),'utf8');
const initial=JSON.parse(fixture.match(/<script id="pd-seed"[^>]*>([\s\S]*?)<\/script>/)[1]);
(async()=>{const browser=await chromium.launch({headless:true,executablePath:process.env.CHROMIUM_PATH||'/usr/bin/chromium',args:['--no-sandbox']});let checks=0;try{
for(const width of [1440,390]){
 const page=await browser.newPage({viewport:{width,height:1000}}),errors=[],writes=[],responses=new Map();let sheet=structuredClone(initial),loseClosing=true,applied=0;
 page.on('pageerror',e=>errors.push(e.message));
 await page.route('**/*',async route=>{const url=new URL(route.request().url()),p=url.pathname;
  if(p.startsWith('/kitchen/assets/')){const f=path.join(kitchen,'public',p.slice('/kitchen/'.length));return route.fulfill({contentType:p.endsWith('.js')?'application/javascript':'text/css',body:fs.readFileSync(f)});}
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
 async function chooseProduct(name){await page.locator('.pd-add .choices__inner').click();await page.locator('.pd-add .choices__input--cloned').fill(name);await page.locator('.pd-add .choices__list--dropdown [data-choice-selectable]').filter({hasText:name}).click();}
 await page.goto('http://kitchen.test/kitchen/production-day');
 await page.waitForSelector('[data-opening-product="1"]');
 assert.equal(await page.locator('[data-opening-product]').count(),4);checks++;
 const cell=page.locator('#pd-body td').filter({has:page.locator('.pd-product-name').filter({hasText:'Mandatory cake'})});const nameRect=await cell.locator('.pd-product-name').boundingBox(),badgesRect=await cell.locator('.pd-product-badges').boundingBox();assert.ok(badgesRect.y>=nameRect.y+nameRect.height,'Opening badges appear below product name');
 assert.equal(await cell.locator('.pd-badge').allTextContents().then(x=>x.join(',')),'MSL');assert.equal(await cell.locator('.pd-mandatory-lock').getAttribute('aria-label'),'Obowiązkowy');
 assert.equal(await cell.locator('.pd-sector,.pd-unit').count(),0);assert.equal(await cell.locator('.pd-product-name').innerText(),'🔒 Mandatory cake');
 const weightedRow=page.locator('tr').filter({has:page.locator('[data-opening-product="6"]')});assert.match(await weightedRow.locator('td').nth(1).innerText(),/1\.25\s+kg/);assert.equal(await weightedRow.locator('.pd-mandatory-lock').count(),0);

 assert.equal(await page.locator('[data-opening-product="2"]').inputValue(),'0');
 assert.equal(await page.locator('[data-page="stock"]').isDisabled(),true);
 await page.locator('[data-opening-product="1"]').locator('..').getByRole('button',{name:'Zwiększ o 1 szt.',exact:true}).click();assert.equal(await page.locator('[data-opening-product="1"]').inputValue(),'11');await page.locator('[data-opening-product="1"]').locator('..').getByRole('button',{name:'Zmniejsz o 1 szt.',exact:true}).click();
 await page.locator('[data-opening-product="6"]').locator('..').getByRole('button',{name:'Zwiększ o 0,1 kg',exact:true}).click();assert.equal(await page.locator('[data-opening-product="6"]').inputValue(),'1.35');await page.locator('[data-opening-product="6"]').fill('1.5');
 await chooseProduct('Extra bun');await page.locator('.pd-add input[type=number]').fill('6');await page.locator('.pd-add button').click();
 assert.equal(await page.locator('[data-opening-product]').last().getAttribute('data-opening-product'),'4');checks++;
 await page.locator('[data-opening-product="2"]').fill('5');await page.locator('[data-opening-product="1"]').fill('8');await page.locator('[data-opening-product="3"]').fill('3');
 await page.getByRole('button',{name:'Otwórz dzień',exact:true}).click();await page.waitForSelector('dialog[open]');
 assert.equal(await page.locator('.pd-decision').count(),2);assert.equal(await page.locator('.pd-decision input[type="number"]').first().isEditable(),false);
 const first=page.locator('.pd-decision').nth(0),second=page.locator('.pd-decision').nth(1);
 await first.getByRole('button',{name:'Pozostaw produkty — przywróć stan',exact:true}).click();
 await second.locator('select').selectOption('damage');await second.getByRole('button',{name:'Potwierdź wyrzucenie',exact:true}).click();
 await page.locator('#pd-dialog-save').click();await page.waitForFunction(()=>document.getElementById('pd-status').textContent==='Dzień otwarty',{},{timeout:5000}).catch(async e=>{console.error(await page.evaluate(()=>({notice:document.getElementById('pd-notice').textContent,error:document.getElementById('pd-dialog-error').textContent,invalid:[...document.querySelectorAll(':invalid')].map(n=>[n.tagName,n.value,n.validationMessage]),status:document.getElementById('pd-status').textContent})),writes,errors);throw e;});
 const actionBox=await page.locator('#pd-body tbody tr:has(td)').first().getByRole('button').boundingBox();assert.ok(actionBox.x>=0&&actionBox.x+actionBox.width<=width,'Stock action visible without horizontal scrolling');
 if(width===390)await page.screenshot({path:path.join(fixtureDir,'mobile-stock.png'),fullPage:true});
 assert.equal(writes.length,1);assert.equal(writes[0].input.lines.find(l=>l.id_product===1).quantity,'10');assert.equal(writes[0].input.lines.find(l=>l.id_product===3).reason,'damage');checks+=2;
 await chooseProduct('Late tart');await page.locator('.pd-add input[type=number]').fill('3');await page.locator('.pd-add button').click();await page.waitForSelector('dialog[open]');await page.locator('#pd-dialog-save').click();await page.waitForSelector('dialog[open]',{state:'hidden'});assert.equal(sheet.products.find(p=>p.id_product===5).stock_quantity,'3.0000');checks++;
 const cake=page.locator('#pd-body tbody tr').filter({hasText:'Mandatory cake'});
 await cake.getByRole('button').click();await page.locator('#pd-dialog-body input[type="number"]').fill('2');await page.locator('#pd-dialog-save').click();await page.waitForSelector('dialog[open]',{state:'hidden'});
 assert.equal(sheet.products.find(p=>p.id_product===2).stock_quantity,'7.0000');checks++;
 await cake.getByRole('button').click();await page.locator('#pd-dialog-body select').first().selectOption('waste');await page.locator('#pd-dialog-body input[type="number"]').fill('1');await page.locator('#pd-dialog-body .pd-reason select').selectOption('damage');await page.locator('#pd-dialog-save').click();await page.waitForSelector('dialog[open]',{state:'hidden'});assert.equal(sheet.products.find(p=>p.id_product===2).stock_quantity,'6.0000');checks++;
 await page.locator('[data-page="closing"]').click();assert.equal(await page.locator('[data-closing-product="1"]').inputValue(),'10');assert.equal(await page.locator('[data-closing-product="1"]').isEditable(),false);checks++;
 await page.getByRole('button',{name:'MSL · średnia trwałość',exact:true}).click();assert.equal(await page.locator('[data-closing-product="6"]').inputValue(),'0');await page.locator('[data-closing-product="6"]').fill('0.125');assert.equal(await page.locator('[data-closing-product="2"]').inputValue(),'0');await page.locator('[data-closing-product="2"]').fill('2');
 await page.getByRole('button',{name:'LSL · długa trwałość',exact:true}).click();assert.equal(await page.locator('[data-closing-product="3"]').inputValue(),'0');checks++;
 await page.getByRole('button',{name:'Zamknij dzień',exact:true}).click();await page.waitForSelector('dialog[open]');
 const disposal=page.locator('.pd-decision');assert.equal(await disposal.count(),4);
 for(let i=0;i<await disposal.count();i++){const row=disposal.nth(i);if((await row.locator('strong').innerText()).includes('SSL'))assert.equal(await row.locator('select').inputValue(),'expiration');else await row.locator('select').selectOption('quality');await row.locator('input[type="checkbox"]').check();}
 await page.locator('#pd-dialog-save').click();await page.waitForSelector('#pd-notice button');
 const key=writes.at(-1).input.request_id,beforeRetry=applied;
 await page.reload();await page.waitForSelector('#pd-notice button');await page.locator('#pd-notice button').click();await page.waitForFunction(()=>document.getElementById('pd-notice').textContent==='Zapisano.');
 assert.equal(writes.at(-1).input.request_id,key);assert.equal(applied,beforeRetry);assert.equal(sheet.status,'CLOSED');assert.equal(sheet.products.find(p=>p.id_product===6).stock_quantity,'1.3750');assert.equal(sheet.products.find(p=>p.id_product===1).stock_quantity,'0.0000');assert.equal(sheet.products.find(p=>p.id_product===3).stock_quantity,'3.0000');checks+=2;
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,'No page overflow '+width);assert.deepEqual(errors,[]);checks++;
 await page.close();
}
// A pending past day offers closing with live stock, never historical production edits.
const late=structuredClone(initial);late.date='2020-01-01';late.status='OPEN';late.late_closing=true;late.default_view='closing';late.read_only=false;
const latePage=await browser.newPage({viewport:{width:390,height:1000}});
await latePage.route('**/*',route=>{const p=new URL(route.request().url()).pathname;if(p.startsWith('/kitchen/assets/')){const f=path.join(kitchen,'public',p.slice('/kitchen/'.length));return route.fulfill({contentType:p.endsWith('.js')?'application/javascript':'text/css',body:fs.readFileSync(f)});}return route.fulfill({contentType:'text/html',body:fixture.replace('data-view="opening"','data-view="closing"').replace(/(<script id="pd-seed"[^>]*>)[\s\S]*?(<\/script>)/,(_,a,b)=>a+JSON.stringify(late).replace(/</g,'\\u003c')+b)});});
await latePage.goto('http://kitchen.test/kitchen/production-day');
assert.equal(await latePage.locator('[data-page="closing"]').getAttribute('aria-current'),'page');
assert.equal(await latePage.locator('[data-closing-product="1"]').inputValue(),'10');
assert.equal(await latePage.getByRole('button',{name:'Zamknij dzień',exact:true}).isEnabled(),true);
await latePage.locator('[data-page="stock"]').click();assert.equal(await latePage.locator('.pd-add').count(),0);assert.equal(await latePage.getByRole('button',{name:'Zmodyfikuj stany bieżące',exact:true}).count(),0);
await latePage.close();checks++;
console.log(`Kitchen production day browser: ${checks} checks PASS (desktop/mobile, opening decisions, production, waste, closing, persisted retry).`);
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1);});
