const {chromium}=require('playwright'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const dir=process.env.KITCHEN_DAY_FIXTURE_DIR||'/workspace/work/kitchen-layout',root=path.resolve(__dirname,'..');
(async()=>{const browser=await chromium.launch({headless:true,executablePath:'/usr/bin/chromium',args:['--no-sandbox']});let checks=0;
try{for(const width of [390,1024])for(const kind of ['inventory','production']){
 const file=kind==='inventory'?'kitchen-waste-portions.html':'kitchen-production-waste-portions.html',html=fs.readFileSync(path.join(dir,file),'utf8'),page=await browser.newPage({viewport:{width,height:1100}}),writes=[],errors=[];
 page.on('pageerror',e=>errors.push(e.message));
 await page.route('**/*',route=>{const req=route.request(),p=new URL(req.url()).pathname;
  if(p.includes('/assets/')){const f=path.join(root,'public',p.slice(p.indexOf('/assets/')+1));return route.fulfill({contentType:f.endsWith('.js')?'application/javascript':'text/css',body:fs.existsSync(f)?fs.readFileSync(f):''});}
  if(req.method()==='POST'){writes.push(req.postDataJSON());return route.fulfill({json:{success:true,data:{items:[]}}});}
  if(p.endsWith('/waste-data'))return route.fulfill({json:{success:false}});
  return route.fulfill({contentType:'text/html',body:html});
 });
 await page.goto('http://kitchen.test/kitchen/'+kind+'/waste');
 if(kind==='inventory')await page.locator('.wp-product button').first().click();
 else {await page.locator('#pwProductSearch').fill('Cro');await page.locator('#pwProducts button').first().click();await page.locator('#pwReasons button').first().click();}
 const editor=page.locator('.kp-portion-editor');await editor.locator('[data-portion-id="12"]').click();await editor.locator('input:not([hidden])').fill('3');
 const native=page.locator(kind==='inventory'?'#wpQuantity':'#pwQuantity');assert.equal(await native.inputValue(),'0.75');checks++;
 if(kind==='inventory'){await page.locator('#wpProductForm button[type=submit]').click();assert.ok((await page.locator('#wpCart').innerText()).includes('3 × 1/4'));await page.locator('#wpSave').click();}
 else await page.locator('#pwSave').click();
 await page.waitForTimeout(100);assert.equal(writes.length,1);assert.equal(writes[0].lines[0].quantity,'0.75');assert.deepEqual(writes[0].lines[0].portion,{id_product_portion:12,source_quantity:'3'});assert.deepEqual(errors,[]);checks+=4;await page.close();
}
console.log('Standalone waste portions: '+checks+' checks PASS (inventory/production reports, 390/1024px, canonical quantity and portion count).');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1)});
