const {chromium}=require('playwright'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const dir=process.env.KITCHEN_DAY_FIXTURE_DIR||'/workspace/work/kitchen-layout',root=path.resolve(__dirname,'..');
const fixture=fs.readFileSync(path.join(dir,'kitchen-runtime-day.html'),'utf8'),seed=JSON.parse(fixture.match(/<script id="pd-seed"[^>]*>([\s\S]*?)<\/script>/)[1]);
(async()=>{const browser=await chromium.launch({headless:true,executablePath:'/usr/bin/chromium',args:['--no-sandbox']});let checks=0;
try{for(const width of [1024,768,390]){
 const sheet=structuredClone(seed);Object.assign(sheet,{wizard_version:2,status:'NOT_OPEN',revision:0,default_view:'opening',read_only:false,late_closing:false,scope_conflict:false,opening_blocker:null,stock_check:null,production_issue:null,overview_only:false,opening:null});
 const half={id:1,type:'ONE_HALF',label:'1/2',numerator:1,denominator:2},quarter={id:2,type:'ONE_QUARTER',label:'1/4',numerator:1,denominator:4},eighth={id:3,type:'ONE_EIGHTH',label:'1/8',numerator:1,denominator:8};
 sheet.products=[[],[half],[half,quarter],[eighth]].map((portions,i)=>({...sheet.products[0],id_product:301+i,name:['Whole only','Half portions','Quarter portions','Eighth portions'][i],unit:'piece',is_piece_based:1,is_divisible:0,stock_quantity:'5.75',in_stock:'5.75',supported:true,default_opening:true,portions,stock_portions:[half,quarter,eighth]}));
 sheet.products.push({...sheet.products[0],id_product:305,name:'Weighted product',unit:'kg',is_piece_based:0,is_divisible:1,portions:[quarter],stock_portions:[]});
 const writes=[],errors=[],page=await browser.newPage({viewport:{width,height:1050},isMobile:width<1000,hasTouch:width<1000});page.on('pageerror',e=>errors.push(e.message));
 await page.route('**/*',route=>{const req=route.request(),p=new URL(req.url()).pathname;
  if(p.includes('/assets/')){const f=path.join(root,'public',p.slice(p.indexOf('/assets/')+1));return route.fulfill({contentType:f.endsWith('.js')?'application/javascript':'text/css',body:fs.existsSync(f)?fs.readFileSync(f):''});}
  if(req.method()==='POST'&&p.endsWith('/prepare')){const input=req.postDataJSON();writes.push(input);sheet.preparation.lines=input.lines;sheet.revision++;sheet.preparation_revision++;return route.fulfill({json:{success:true,data:sheet}});}
  if(p.endsWith('/production-day/data'))return route.fulfill({json:{success:true,data:sheet}});
  if(p.includes('/ajax/'))return route.fulfill({json:{success:true,items:[],count:0,total:0}});
  return route.fulfill({contentType:'text/html',body:fixture.replace(/(<script id="pd-seed"[^>]*>)[\s\S]*?(<\/script>)/,'$1'+JSON.stringify(sheet)+'$2')});
 });
 await page.goto('http://kitchen.test/kitchen/production-day');
 assert.equal(await page.locator('.pd-carry-stepper').count(),5);assert.equal(await page.locator('.pd-carry-stepper .kp-portion-editor').count(),0);checks+=2;
 for(const [id,step] of [[301,null],[302,'0.5'],[303,'0.25'],[304,'0.125'],[305,null]]){
  const input=page.locator('[data-opening-product="'+id+'"]'),control=input.locator('..');
  assert.equal(await control.locator('.pd-fraction-delta').count(),step?2:0);assert.equal(await control.locator('[data-quantity-delta="5"]').count(),step?0:1);checks+=2;
  if(!step)continue;
  assert.equal(await control.locator('button').first().innerText(),'−'+step);assert.equal(await control.locator('button').last().innerText(),'+'+step);checks+=2;
  await control.locator('[data-quantity-delta="-'+step+'"]').click();assert.equal(await input.inputValue(),String(5.75-Number(step)));checks++;
  await control.locator('[data-quantity-delta="'+step+'"]').click();assert.equal(await input.inputValue(),'5.75');checks++;
  await control.locator('[data-quantity-delta="'+step+'"]').click();assert.equal(await input.inputValue(),'5.75','Cannot exceed previous stock');checks++;
  await control.locator('[data-quantity-delta="-10"]').click();await control.locator('[data-quantity-delta="-'+step+'"]').click();assert.equal(await input.inputValue(),'0','Cannot go negative');checks++;
  await control.locator('[data-quantity-delta="'+step+'"]').click();assert.equal(await input.inputValue(),step);checks++;
  const layout=await control.evaluate(node=>{const buttons=[...node.querySelectorAll('button')],rects=buttons.map(b=>b.getBoundingClientRect());const bounds=node.getBoundingClientRect();return {inside:rects.every(r=>r.left>=bounds.left&&r.right<=bounds.right),fits:buttons.every(b=>{const range=document.createRange();range.selectNodeContents(b);return b.scrollWidth<=b.clientWidth&&range.getClientRects().length===1;}),separate:rects.every((a,i)=>rects.every((b,j)=>i===j||a.right<=b.left||b.right<=a.left||a.bottom<=b.top||b.bottom<=a.top)),touch:rects.every(r=>r.height>=44&&r.width>=44)};});
  assert.deepEqual(layout,{inside:true,fits:true,separate:true,touch:true});checks++;
 }
 // Advance with unchanged carry; today's production uses the same compact controls.
 for(const p of sheet.products)await page.locator('[data-opening-product="'+p.id_product+'"]').fill('5.75');
 await page.locator('#pd-wizard-next').click();await page.locator('#pd-wizard-next').click();
 assert.equal(await page.locator('[data-opening-field="production_quantity"]').count(),5);assert.equal(await page.locator('.pd-carry-stepper .kp-portion-editor').count(),0);checks+=2;
 for(const [id,step]of [[301,null],[302,'0.5'],[303,'0.25'],[304,'0.125'],[305,null]]){
  const input=page.locator('[data-opening-product="'+id+'"]'),control=input.locator('..');
  assert.equal(await control.locator('.pd-fraction-delta').count(),step?2:0);assert.equal(await control.locator('[data-quantity-delta="5"]').count(),step?0:1);checks+=2;
  if(!step)continue;
  await control.locator('[data-quantity-delta="'+step+'"]').click();assert.equal(await input.inputValue(),step);await control.locator('[data-quantity-delta="-'+step+'"]').click();assert.equal(await input.inputValue(),'0');checks+=2;
  await input.fill('7');await control.locator('[data-quantity-delta="'+step+'"]').click();assert.equal(await input.inputValue(),String(7+Number(step)),'Production can exceed yesterday stock');checks++;
  const layout=await control.evaluate(node=>{const bounds=node.getBoundingClientRect(),buttons=[...node.querySelectorAll('button')],rects=buttons.map(b=>b.getBoundingClientRect());return rects.every(r=>r.left>=bounds.left&&r.right<=bounds.right&&r.width>=44&&r.height>=44)&&buttons.every(b=>{const range=document.createRange();range.selectNodeContents(b);return range.getClientRects().length===1;});});assert.equal(layout,true);checks++;
 }
 await page.locator('[data-opening-product="303"]').locator('..').screenshot({path:path.join(dir,'carry-fractions-'+width+'.png')});assert.deepEqual(errors,[]);assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);checks+=2;// Step 6 reuses native controls and persists only preparation quantities.
 Object.assign(sheet,{status:'OPEN',default_view:'stock',can_prepare:true,preparation_revision:0,preparation:{target_date:'2026-11-10',lines:[]}});
 sheet.products.forEach(p=>{p.is_pdm=true;p.is_active=1;p.sale_enabled=1;});sheet.preparation_products=sheet.products;
 const beforeStock=sheet.products.map(p=>p.in_stock);await page.reload();await page.locator('[data-wizard-step="5"]').click();
 assert.equal(await page.locator('[data-preparation-product]').count(),5);assert.equal(await page.locator('.pd-carry-stepper .kp-portion-editor').count(),0);checks+=2;
 for(const [id,step]of [[301,null],[302,'0.5'],[303,'0.25'],[304,'0.125'],[305,null]]){
  const input=page.locator('[data-preparation-product="'+id+'"]'),control=input.locator('..');
  assert.equal(await control.locator('.pd-fraction-delta').count(),step?2:0);assert.equal(await control.locator('[data-quantity-delta="5"]').count(),step?0:1);checks+=2;
  if(!step)continue;
  await control.locator('[data-quantity-delta="'+step+'"]').click();assert.equal(await input.inputValue(),step);await control.locator('[data-quantity-delta="-'+step+'"]').click();assert.equal(await input.inputValue(),'0');checks+=2;
  await input.fill('7');await control.locator('[data-quantity-delta="'+step+'"]').click();assert.equal(await input.inputValue(),String(7+Number(step)),'Tomorrow preparation is independent of current stock');checks++;
  const layout=await control.evaluate(node=>{const bounds=node.getBoundingClientRect(),buttons=[...node.querySelectorAll('button')],rects=buttons.map(b=>b.getBoundingClientRect());return rects.every(r=>r.left>=bounds.left&&r.right<=bounds.right&&r.width>=44&&r.height>=44)&&rects.every((a,i)=>rects.every((b,j)=>i===j||a.right<=b.left||b.right<=a.left||a.bottom<=b.top||b.bottom<=a.top))&&buttons.every(b=>{const range=document.createRange();range.selectNodeContents(b);return range.getClientRects().length===1;});});assert.equal(layout,true);checks++;
 }
 await page.locator('[data-preparation-product="303"]').locator('..').screenshot({path:path.join(dir,'tomorrow-fractions-'+width+'.png')});
 await Promise.all([page.waitForResponse(r=>r.url().endsWith('/prepare')&&r.request().method()==='POST'),page.locator('#pd-wizard-next').click()]);
 assert.equal(writes.length,1);assert.equal(writes[0].lines.find(l=>l.id_product===303).quantity,'7.25');assert.deepEqual(sheet.products.map(p=>p.in_stock),beforeStock);checks+=3;
 await page.reload();await page.locator('[data-wizard-step="5"]').click();assert.equal(await page.locator('[data-preparation-product="303"]').inputValue(),'7.25');assert.deepEqual(errors,[]);checks+=2;
 await page.close();
}
console.log('Carry, today and tomorrow fraction controls: '+checks+' checks PASS (configured-only fractions, native totals, limits, touch layout at 1024/768/390px).');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1)});
