const {chromium}=require('playwright'), assert=require('node:assert/strict'), fs=require('node:fs'),path=require('node:path');
const root=path.resolve(__dirname,'..'),dir=process.env.KITCHEN_DELIVERY_FIXTURE_DIR||path.join(require('node:os').tmpdir(),'kitchen-deliveries');
const read=name=>fs.readFileSync(path.join(dir,name+'.html'),'utf8');
(async()=>{const browser=await chromium.launch({headless:true,executablePath:'/usr/bin/chromium',args:['--no-sandbox']});let checks=0;
try{
 for(const [width,height] of [[1024,768],[768,1024],[390,844],[1280,900]]){
  const page=await browser.newPage({viewport:{width,height}});let mode='success',writes=[];const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('**/*',async route=>{
   const request=route.request(),url=new URL(request.url()),p=url.pathname;
   if(p.endsWith('/receive')&&request.method()==='POST'){
    writes.push(request.postDataJSON());
    if(mode==='mapping'&&writes.length===1)return route.fulfill({status:422,json:{success:false,reason:'DELIVERY_CATALOG_MAPPING_MISMATCH',errors:{unmapped_products:[{material_name:'Unknown <b>product</b>',supplier_sku:'UNKNOWN'}]}}});
    if(mode==='unknown')return route.fulfill({status:500,json:{success:false}});
    if(mode==='stale')return route.fulfill({status:409,json:{success:false,reason:'DELIVERY_SHIPMENT_UPDATED'}});
    if(mode==='shift')return route.fulfill({status:403,json:{success:false,reason:'KITCHEN_SHIFT_INVALID'}});
    return route.fulfill({json:{success:true,data:{success_insert_delivery:true,success_recalculate_costs:mode!=='costs',excluded_unmapped_products:mode==='mapping'?[{}]:[]}}});
   }
   if(p.includes('/assets/')){const file=path.join(root,'public',p.slice(p.indexOf('/assets/')));if(fs.existsSync(file)&&fs.statSync(file).isFile())return route.fulfill({path:file});return route.fulfill({status:404,body:''});}
   if(p==='/kitchen/deliveries/40')return route.fulfill({contentType:'text/html',body:read('receive')});
   if(p==='/kitchen/deliveries')return route.fulfill({contentType:'text/html',body:read('index')});
   return route.fulfill({json:[]});
  });
  await page.goto('http://kitchen.test/kitchen/deliveries');
  assert.equal(await page.locator('.dl-order').count(),1);await page.locator('.dl-order').click();
  assert.equal(await page.locator('.dl-line').count(),2);assert.equal(await page.locator('.dl-line input').first().inputValue(),'2');checks+=3;
  assert.equal(await page.locator('.dl-stepper button').first().evaluate(e=>e.getBoundingClientRect().height>=48),true);checks++;
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);checks++;
  await page.locator('.dl-line input').first().fill('2.5');await page.locator('#dlSave').click();assert.equal(writes.length,0);await page.locator('#dlCancel').click();assert.equal(writes.length,0);checks+=2;
  await page.screenshot({path:path.join(dir,`delivery-${width}.png`),fullPage:true});
  await page.locator('#dlSave').click();await page.locator('#dlConfirmSave').click();await page.waitForFunction(()=>document.getElementById('dlSave').hidden);
  assert.equal(writes.length,1);assert.equal(Object.values(writes[0].quantities)[0],'2.5');assert.equal(writes[0].shipment_revision,3);assert.equal('materials' in writes[0],false);assert.equal(await page.locator('fieldset').evaluate(e=>e.disabled),true);checks+=5;
  for(const scenario of ['mapping','unknown','stale','costs','shift']){
   mode=scenario;writes=[];await page.goto('http://kitchen.test/kitchen/deliveries/40');await page.locator('#dlSave').click();await page.locator('#dlConfirmSave').click();
   if(scenario==='mapping'){
    await page.locator('#dlIssues li').waitFor();assert.equal(await page.locator('#dlIssues b').count(),0);assert.equal(await page.locator('#dlIssues li').textContent(),'Unknown <b>product</b>');await page.locator('#dlConfirmSave').click();await page.waitForFunction(()=>document.getElementById('dlSave').hidden);assert.equal(writes.length,2);assert.equal(writes[1].confirm_exclude_unmapped_products,true);checks+=4;
   }else{
    await page.locator('#dlCheck').waitFor({state:'visible'});assert.equal(writes.length,1);assert.equal(await page.locator('fieldset').evaluate(e=>e.disabled),true);checks+=2;
    if(scenario==='costs'){assert.match(await page.locator('#dlNotice').textContent(),/Nie zapisuj ponownie/);checks++;}
   }
  }
  assert.deepEqual(errors,[]);checks++;await page.close();
 }
 console.log(`Kitchen delivery browser: ${checks} checks PASS (4 layouts, correction, confirmation, success, exclusions, stale shipment, uncertain write, cost warning, expired shift)`);
}finally{await browser.close();}})().catch(error=>{console.error(error);process.exit(1)});
