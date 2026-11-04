const {chromium}=require('playwright'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const dir=process.env.KITCHEN_DAY_FIXTURE_DIR||path.join(require('node:os').tmpdir(),'kitchen-day-mvp'),root=path.resolve(__dirname,'..');
const fixture=fs.readFileSync(path.join(dir,'kitchen-runtime-day.html'),'utf8'),seed=JSON.parse(fixture.match(/<script id="pd-seed"[^>]*>([\s\S]*?)<\/script>/)[1]);
(async()=>{const browser=await chromium.launch({headless:true,executablePath:'/usr/bin/chromium',args:['--no-sandbox']});let checks=0;
try{for(const width of [1024,768,390]){
 const page=await browser.newPage({viewport:{width,height:1000},isMobile:width<1000,hasTouch:width<1000}),errors=[],writes=[],visits=[];
 page.on('pageerror',e=>errors.push(e.message));const sheet=structuredClone(seed);Object.assign(sheet,{wizard_version:2,production_plan_available:true,products:sheet.products.filter(p=>[1,4].includes(p.id_product)),absence_reasons:['other']});
 Object.assign(sheet.products[0],{is_pdm:true,planned_production_quantity:3});Object.assign(sheet.products[1],{planned_production_quantity:12});
 let failPlan=false,planVariant='ready';
 await page.route('**/*',async route=>{const req=route.request(),url=new URL(req.url()),p=url.pathname;
  if(p.includes('/assets/')){const f=path.join(root,'public',p.slice(p.indexOf('/assets/')+1));return route.fulfill({contentType:p.endsWith('.js')?'application/javascript':'text/css',body:fs.existsSync(f)?fs.readFileSync(f):''});}
  if(p.includes('/ajax/'))return route.fulfill({json:{success:true,total:0,count:0}});
  if(p.endsWith('/production-plan')){visits.push(url.searchParams.get('offset')||'0');await new Promise(r=>setTimeout(r,200));return route.fulfill({contentType:'text/html',body:fs.readFileSync(path.join(dir,'kitchen-plan-'+(failPlan?'error':planVariant)+'.html'),'utf8')});}
  if(p.endsWith('/production-day/data'))return route.fulfill({json:{success:true,data:sheet}});
  if(req.method()==='POST'){const input=req.postDataJSON();writes.push(input);sheet.status='OPEN';sheet.default_view='stock';sheet.revision=1;sheet.products.forEach(product=>product.stock_quantity=product.in_stock=input.lines.find(l=>l.id_product===product.id_product).quantity);return route.fulfill({json:{success:true,data:{status:'OPEN'}}});}
  return route.fulfill({contentType:'text/html',body:fixture.replace(/(<script id="pd-seed"[^>]*>)[\s\S]*?(<\/script>)/,(_,a,b)=>a+JSON.stringify(sheet)+b)});
 });
 await page.goto('http://kitchen.test/kitchen/production-plan');assert.equal(await page.locator('[data-plan-offset]').count(),3);assert.equal(await page.locator('[data-plan-product="101"]').count(),1);assert.ok((await page.locator('.pp-plan-quantity').innerText()).includes('12'));assert.equal(await page.locator('input[type=date]').count(),0);checks+=4;
 assert.equal(await page.locator('.pp-cycle-heading').count(),3);
 assert.deepEqual(await page.locator('[data-plan-product="101"] [data-plan-cycle]').allTextContents(),['7 szt.','3 szt.','2 szt.']);
 assert.ok((await page.locator('.pp-cycle-heading').first().innerText()).includes('06:00–10:00'));
 assert.equal(await page.locator('.pp-cycle-note').count(),0);checks+=4;
 await page.screenshot({path:path.join(dir,'production-plan-'+width+'.png')});
 const tomorrow=page.locator('[data-plan-offset="1"]');await tomorrow.click({noWaitAfter:true});await page.waitForURL(url=>url.searchParams.get('offset')==='1');await page.waitForLoadState();assert.equal(visits.at(-1),'1');await page.locator('[data-plan-offset="2"]').click();assert.equal(visits.at(-1),'2');checks+=2;
 planVariant='one-cycle';await page.reload();assert.equal(await page.locator('.pp-cycle-heading').count(),1);assert.ok((await page.locator('[data-plan-cycle="c1"]').innerText()).includes('12'));checks+=2;
 planVariant='no-cycles';await page.reload();assert.equal(await page.locator('.pp-cycle-heading').count(),0);assert.equal(await page.locator('.pp-cycle-note').isVisible(),true);assert.ok((await page.locator('.pp-plan-quantity').innerText()).includes('12'));checks+=3;
 planVariant='missing-category';await page.reload();assert.deepEqual(await page.locator('[data-plan-cycle]').allTextContents(),['—','—','—']);checks++;
 planVariant='ready';
 failPlan=true;await page.reload();assert.equal(await page.locator('[data-plan-product]').count(),0);assert.equal(await page.locator('[role=alert]').count()>0,true);checks+=2;
 await page.goto('http://kitchen.test/kitchen/production-day');assert.equal(await page.locator('[data-opening-product="1"]').inputValue(),'10');assert.equal(await page.locator('[data-opening-product="4"]').count(),0);await page.locator('#pd-wizard-next').click();assert.equal(await page.locator('[data-opening-product="1"]').inputValue(),'0','Plan never pre-fills overnight production');await page.locator('#pd-wizard-next').click();
 assert.equal(await page.locator('[data-opening-product="1"]').inputValue(),'3');assert.equal(await page.locator('[data-opening-product="4"]').inputValue(),'12','Positive plan includes a product with no stock and no mandatory flag');assert.equal(writes.length,0);checks+=6;
 await page.locator('[data-opening-product="4"]').fill('9');await page.locator('[data-wizard-step="1"]').click();await page.locator('[data-wizard-step="2"]').click();assert.equal(await page.locator('[data-opening-product="4"]').inputValue(),'9','Navigation does not replace manual edits with the plan');await page.locator('#pd-wizard-next').click();await page.waitForFunction(()=>document.getElementById('pd-status').textContent==='Dzień otwarty');assert.equal(writes.length,1);
 const bread=writes[0].lines.find(l=>l.id_product===1),bun=writes[0].lines.find(l=>l.id_product===4);assert.equal(bread.quantity,'13');assert.equal(bread.opening_details.carried_quantity,'10');assert.equal(bread.opening_details.overnight_quantity,'0');assert.equal(bread.opening_details.production_quantity,'3');assert.equal(bun.quantity,'9');checks+=7;
 assert.deepEqual(errors,[]);assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);await page.screenshot({path:path.join(dir,'plan-prefill-stock-'+width+'.png')});checks+=2;
 sheet.status='NOT_OPEN';sheet.production_plan_available=false;sheet.products.forEach(p=>{delete p.planned_production_quantity;});await page.reload();await page.locator('#pd-wizard-next').click();await page.locator('#pd-wizard-next').click();assert.equal(await page.locator('#pd-body [role=alert]').count(),1);assert.equal(await page.locator('[data-opening-product="1"]').inputValue(),'0');checks+=2;await page.close();
 }
 console.log('Kitchen production plan: '+checks+' checks PASS (3 layouts, three day links, unavailable plan, editable prefill, zero-stock products, no writes before opening).');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1)});
