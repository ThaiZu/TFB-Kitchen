const reviewCarry=require('./helpers/carry-review.cjs');
const {chromium}=require('playwright'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const dir=process.env.KITCHEN_DAY_FIXTURE_DIR||'/workspace/work/kitchen-layout',root=path.resolve(__dirname,'..');
const fixture=fs.readFileSync(path.join(dir,'kitchen-runtime-day.html'),'utf8'),seed=JSON.parse(fixture.match(/<script id="pd-seed"[^>]*>([\s\S]*?)<\/script>/)[1]);
(async()=>{const browser=await chromium.launch({headless:true,executablePath:'/usr/bin/chromium',args:['--no-sandbox']});let checks=0;
try{for(const width of [1024,768,390]){
 const sheet=structuredClone(seed);Object.assign(sheet,{wizard_version:2,status:'OPEN',default_view:'stock',read_only:false,late_closing:false,scope_conflict:false,opening_blocker:null,stock_check:null,production_issue:null,overview_only:false,opening:null});
 sheet.products=[{...sheet.products[0],id_product:90,name:'Portioned cake',stock_quantity:'1.125',in_stock:'1.125',shelf_life_category:'MSL',unit:'piece',is_piece_based:1,is_divisible:0,portions:[{id:901,type:'ONE_HALF',label:'1/2',numerator:1,denominator:2},{id:902,type:'ONE_QUARTER',label:'1/4',numerator:1,denominator:4},{id:903,type:'ONE_EIGHTH',label:'1/8',numerator:1,denominator:8}]}];
 sheet.preparation_products=[];const writes=[],errors=[];
 const page=await browser.newPage({viewport:{width,height:1050},isMobile:width<1000,hasTouch:width<1000});page.on('pageerror',e=>errors.push(e.message));
 await page.route('**/*',route=>{const req=route.request(),p=new URL(req.url()).pathname;
  if(p.includes('/assets/')){const f=path.join(root,'public',p.slice(p.indexOf('/assets/')+1));return route.fulfill({contentType:f.endsWith('.js')?'application/javascript':'text/css',body:fs.existsSync(f)?fs.readFileSync(f):''});}
  if(req.method()==='POST'){const body=req.postDataJSON();if(p.endsWith('/photos'))return route.fulfill({json:{success:true,data:{id:body.upload_id}}});
   writes.push(body);if(p.endsWith('/adjust')){const line=body.lines[0],product=sheet.products.find(p=>p.id_product===line.id_product);product.in_stock=product.stock_quantity=String(Number(product.in_stock)+(line.mode==='waste'?-1:1)*Number(line.quantity));sheet.revision++;product.last_movement_id=String(Number(product.last_movement_id)+1);}
   if(p.endsWith('/open')){sheet.status='OPEN';sheet.revision++;}
   return route.fulfill({json:{success:true,data:sheet}});
  }
  if(p.endsWith('/production-day/data'))return route.fulfill({json:{success:true,data:sheet}});
  if(p.includes('/ajax/'))return route.fulfill({json:{success:true,items:[],count:0,total:0}});
  return route.fulfill({contentType:'text/html',body:fixture.replace('data-view="opening"',sheet.status==='OPEN'?'data-view="stock"':'data-view="opening"').replace(/(<script id="pd-seed"[^>]*>)[\s\S]*?(<\/script>)/,'$1'+JSON.stringify(sheet)+'$2')});
 });
 await page.goto('http://localhost/kitchen/production-day');await page.locator('[data-stock-product="90"] button').click();
 const editor=page.locator('.pd-adjust-input .kp-portion-editor');await editor.locator('[data-portion-id="901"]').click();
 assert.equal(await editor.locator('.kp-portion-caption').isVisible(),true);for(const button of await editor.locator('.kp-portion-options button').all()){const box=await button.boundingBox();assert.ok(box.width>=72&&box.height>=56,'Portion choice is tablet-sized');}checks+=2;
 await editor.locator('input:not([hidden])').fill('4');assert.equal(await page.locator('.pd-adjust-after strong').innerText(),'3.125 szt.');checks++;
 await page.locator('#pd-dialog-save').click();await page.waitForSelector('dialog[open]',{state:'hidden'});
 assert.equal(writes[0].lines[0].quantity,'2');assert.deepEqual(writes[0].lines[0].portion,{id_product_portion:901,source_quantity:'4'});assert.equal(sheet.products[0].in_stock,'3.125');checks+=3;
 await page.locator('[data-stock-product="90"] button').click();await page.locator('[data-adjust-mode="waste"]').click();assert.equal(await editor.locator('.kp-portion-options').isVisible(),true,'Waste operation also offers configured portions');checks++;
 await editor.locator('[data-portion-id="902"]').click();await editor.locator('input:not([hidden])').fill('3');
 assert.equal(await page.locator('.pd-adjust-after strong').innerText(),'2.375 szt.');assert.equal(await page.locator('#pd-dialog-save').isDisabled(),true);checks+=2;
 await page.locator('[data-adjust-reason="tasting"]').click();
 const png=await page.evaluate(()=>{const c=document.createElement('canvas');c.width=c.height=8;c.getContext('2d').fillRect(0,0,8,8);return c.toDataURL('image/png').split(',')[1];});
 await page.locator('[data-waste-photo-input="gallery"]').setInputFiles({name:'waste.png',mimeType:'image/png',buffer:Buffer.from(png,'base64')});await page.waitForFunction(()=>!document.getElementById('pd-dialog-save').disabled);
 await editor.locator('input:not([hidden])').fill('99');assert.equal(await page.locator('#pd-dialog-save').isDisabled(),true);await editor.locator('input:not([hidden])').fill('3');checks++;
 await page.locator('#pd-dialog-save').click();await page.waitForSelector('dialog[open]',{state:'hidden'});
 assert.equal(writes[1].lines[0].quantity,'0.75');assert.deepEqual(writes[1].lines[0].portion,{id_product_portion:902,source_quantity:'3'});assert.equal(writes[1].lines[0].photo_ids.length,1);assert.equal(sheet.products[0].in_stock,'2.375');checks+=4;
 // Quick increments now count the selected portion rather than whole products.
 await page.locator('[data-stock-product="90"] button').click();await editor.locator('[data-portion-id="903"]').click();await editor.locator('input:not([hidden])').fill('1');
 await page.locator('.pd-adjust-shortcuts [data-quantity-delta="5"]').click();assert.equal(await editor.locator('input:not([hidden])').inputValue(),'6');assert.equal(await page.locator('.pd-adjust-after strong').innerText(),'3.125 szt.');checks+=2;
 await page.locator('.pd-adjust-shortcuts [data-quantity-delta="-5"]').click();assert.equal(await editor.locator('input:not([hidden])').inputValue(),'1');checks++;
 await page.screenshot({path:path.join(dir,'portion-adjust-'+width+'.png')});await page.locator('#pd-dialog-cancel').click();
 // The same portions work in opening shortage allocation and both production steps.
 Object.assign(sheet,{status:'NOT_OPEN',revision:0,opening:null});sheet.products[0].is_pdm=true;sheet.products[0].default_opening=true;
 await page.reload();await reviewCarry(page,{90:'0.375'});
 const shortage=page.locator('.pd-decision');await shortage.locator('[data-waste-reason="expiration"]').click();
 const allocation=shortage.locator('[data-allocation-quantity="expiration"]').locator('..');await allocation.locator('[data-portion-id="902"]').click();await allocation.locator('input:not([hidden])').fill('8');
 await shortage.locator('[data-waste-photo-input="gallery"]').setInputFiles({name:'shortage.png',mimeType:'image/png',buffer:Buffer.from(png,'base64')});
 const confirm=shortage.locator('.pd-disposal-confirm');await page.waitForFunction(()=>!document.querySelector('.pd-disposal-confirm').disabled);await confirm.click();
 await page.locator('#pd-dialog-save').click();await page.locator('#pd-dialog-save').click();await page.waitForSelector('dialog[open]',{state:'hidden'});
 let stepEditor=page.locator('[data-opening-product="90"]').locator('..').locator('.kp-portion-options');await stepEditor.locator('[data-portion-id="901"]').click();await page.locator('[data-opening-product="90"]').locator('..').locator('input:not([hidden])').fill('1');await page.locator('#pd-wizard-next').click();
 const todayInput=page.locator('[data-opening-product="90"]'),todayControl=todayInput.locator('..');assert.equal(await todayControl.locator('.kp-portion-options').count(),0);await todayControl.locator('[data-quantity-delta="0.125"]').click();await todayControl.locator('[data-quantity-delta="0.125"]').click();assert.equal(await todayInput.inputValue(),'0.25');checks+=2;await page.locator('#pd-wizard-next').click();
 await page.waitForFunction(()=>document.getElementById('pd-status').textContent==='Dzień otwarty');
 const opening=writes[2].lines[0];assert.equal(opening.quantity,'1.125');assert.deepEqual(opening.opening_details.portion_details,{overnight_quantity:{id_product_portion:901,source_quantity:'1'}});
 assert.deepEqual(opening.waste_allocations[0].portion,{id_product_portion:902,source_quantity:'8'});assert.equal(opening.waste_allocations[0].quantity,'2');checks+=4;
 // Adding a zero-stock product asks for product first, then quantity exactly once.
 const abricot={...sheet.products[0],id_product:91,name:'Abricot',in_stock:'0',stock_quantity:'0',last_movement_id:'0',portions:sheet.products[0].portions.map(p=>({...p,id:p.id+10000}))};
 sheet.products.push(abricot,{...abricot,id_product:92,name:'Weighted addition',unit:'kg',is_piece_based:0,is_divisible:1,portions:[]});await page.reload();
 const panel=page.locator('#pd-add-panel');await page.locator('#pd-add-toggle').click();
 assert.equal(await panel.locator('input[type=number]').count(),0);assert.equal(await panel.locator('.kp-portion-editor').count(),0);assert.equal(await panel.getByRole('button',{name:'Ustaw ilość',exact:true}).isDisabled(),true);checks+=3;
 async function pick(name){await panel.locator('.choices__inner').click();await panel.locator('.choices__input--cloned').fill(name);await panel.locator('.choices__list--dropdown [data-choice-selectable]').filter({hasText:name}).click();}
 const nextBounds=await panel.getByRole('button',{name:'Ustaw ilość',exact:true}).boundingBox(),selectBounds=await panel.locator('.choices__inner').boundingBox();assert.ok(nextBounds.height>=44&&nextBounds.x>=0&&nextBounds.x+nextBounds.width<=width,'Selection action fits the tablet footer');assert.ok(Math.abs(nextBounds.y+nextBounds.height-selectBounds.y-selectBounds.height)<4,'Product and next action share a row');checks+=2;
 await page.screenshot({path:path.join(dir,'abricot-product-picker-'+width+'.png')});
 await pick('Abricot');const beforeAdd=writes.length;await panel.getByRole('button',{name:'Ustaw ilość',exact:true}).click();
 assert.equal(await panel.isVisible(),false);assert.equal(await page.locator('[data-adjust-mode="produce"]').count(),1);assert.equal(await page.locator('[data-adjust-mode="waste"]').count(),0);assert.equal(writes.length,beforeAdd);assert.ok((await page.locator('.pd-portion-hint').innerText()).includes('liczbę takich porcji'));checks+=5;
 await page.locator('#pd-dialog-cancel').click();assert.equal(writes.length,beforeAdd);assert.equal(abricot.in_stock,'0');checks+=2;
 await page.locator('#pd-add-toggle').click();await panel.getByRole('button',{name:'Ustaw ilość',exact:true}).click();
 await editor.locator('[data-portion-id="10902"]').click();await editor.locator('input:not([hidden])').fill('3');assert.equal(await page.locator('.pd-adjust-after strong').innerText(),'0.75 szt.');checks++;
 await page.screenshot({path:path.join(dir,'abricot-add-product-'+width+'.png')});await page.locator('#pd-dialog-save').click();await page.waitForSelector('dialog[open]',{state:'hidden'});
 const added=writes.at(-1).lines[0];assert.equal(writes.length,beforeAdd+1);assert.equal(added.id_product,91);assert.equal(added.mode,'produce');assert.equal(added.quantity,'0.75');assert.deepEqual(added.portion,{id_product_portion:10902,source_quantity:'3'});assert.equal(abricot.in_stock,'0.75');assert.equal(await page.locator('[data-stock-product="91"]').count(),1);checks+=7;
 await page.locator('#pd-add-toggle').click();await pick('Weighted addition');await panel.getByRole('button',{name:'Ustaw ilość',exact:true}).click();assert.equal(await page.locator('.pd-adjust-input .kp-portion-editor').count(),0);
 await page.locator('.pd-adjust-input input').fill('0.125');await page.locator('#pd-dialog-save').click();await page.waitForSelector('dialog[open]',{state:'hidden'});assert.equal(writes.at(-1).lines[0].quantity,'0.125');assert.equal(writes.at(-1).lines[0].id_product,92);checks+=3;
 // A piece product without saleable portion records still has stock fractions from API.
 const plain=sheet.products.find(p=>p.id_product===91);plain.portions=[];plain.in_stock=plain.stock_quantity='2';plain.stock_portions=[{id:null,type:'ONE_HALF',label:'1/2',numerator:1,denominator:2},{id:null,type:'ONE_QUARTER',label:'1/4',numerator:1,denominator:4},{id:null,type:'ONE_EIGHTH',label:'1/8',numerator:1,denominator:8}];
 await page.reload();await page.locator('[data-stock-product="91"] button').click();
 await editor.locator('[data-portion-id="ONE_HALF"]').click();await editor.locator('input:not([hidden])').fill('3');
 assert.equal(await page.locator('.pd-adjust-shortcuts [data-quantity-delta="-1"]').isEnabled(),true);await page.locator('.pd-adjust-shortcuts [data-quantity-delta="-1"]').click();assert.equal(await editor.locator('input:not([hidden])').inputValue(),'2');await page.locator('.pd-adjust-shortcuts [data-quantity-delta="1"]').click();checks+=2;
 await page.screenshot({path:path.join(dir,'stock-default-portions-'+width+'.png')});
 await page.locator('#pd-dialog-save').click();await page.waitForSelector('dialog[open]',{state:'hidden'});
 assert.equal(writes.at(-1).lines[0].quantity,'1.5');assert.equal(writes.at(-1).lines[0].portion,undefined);checks+=2;
 await page.locator('[data-stock-product="91"] button').click();await page.locator('[data-adjust-mode="waste"]').click();
 await editor.locator('[data-portion-id="ONE_QUARTER"]').click();await editor.locator('input:not([hidden])').fill('2');
 assert.equal(await page.locator('.pd-adjust-after strong').innerText(),'3 szt.');checks++;
 await page.locator('[data-adjust-reason="tasting"]').click();await page.locator('[data-waste-photo-input="gallery"]').setInputFiles({name:'waste.png',mimeType:'image/png',buffer:Buffer.from(png,'base64')});
 await page.waitForFunction(()=>!document.getElementById('pd-dialog-save').disabled);await page.locator('#pd-dialog-save').click();await page.waitForSelector('dialog[open]',{state:'hidden'});
 assert.equal(writes.at(-1).lines[0].quantity,'0.5');assert.equal(writes.at(-1).lines[0].portion,undefined);assert.equal(plain.in_stock,'3');checks+=3;
 assert.deepEqual(errors,[]);assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);checks+=2;await page.close();
}
console.log('Kitchen product portions: '+checks+' checks PASS (production, waste/photos, source evidence, limits, +/- portions, 1024/768/390px).');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1)});
