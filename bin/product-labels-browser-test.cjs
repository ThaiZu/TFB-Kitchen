const {chromium}=require('playwright'),fs=require('fs'),nodePath=require('path'),assert=require('node:assert/strict');
const fixtureDir=process.env.LABEL_FIXTURE_DIR||'/tmp',panelRoot=process.env.LABEL_PANEL_ROOT||nodePath.resolve(__dirname,'../../TFB-Panel');
(async()=>{const browser=await chromium.launch({executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || (fs.existsSync('/usr/bin/chromium')?'/usr/bin/chromium':undefined),headless:true,args:['--no-sandbox']});let checks=0;
try{
 for(const width of [1366,768,390]){
  const page=await browser.newPage({viewport:{width,height:width===1366?768:1024}}),errors=[],posts=[];let fail=true,stored=new Map(),jobs=[];
  if(width===390)await page.addInitScript(()=>Object.defineProperty(crypto,'randomUUID',{value:undefined}));
  page.on('pageerror',e=>errors.push(e.message));page.on('dialog',d=>d.accept());
  await page.route('https://labels.test/**',async route=>{const url=new URL(route.request().url()),path=url.pathname;
   if(path.endsWith('/product-labels.js'))return route.fulfill({contentType:'application/javascript',body:fs.readFileSync('public/assets/js/product-labels.js','utf8')});
   if(path.endsWith('/catalog'))return route.fulfill({json:{success:true,data:{printer_configured:true,products:[{id:100,name:'Bread <script>window.labelXss=1</script>',portions:[{id:20,name:'1/2'}]}]}}});
   if(path.endsWith('/jobs'))return route.fulfill({json:{success:true,data:{jobs}}});
   if(path.endsWith('/create')){const body=route.request().postDataJSON();posts.push(body);if(!stored.has(body.request_id)){stored.set(body.request_id,1);jobs=[{id:'1',status:'uncertain',created_at:'2026-11-16 08:00:00',payload_json:JSON.stringify({products:[{ProductName:'Bread <script>window.labelXss=1</script>'}]})}];}if(fail){fail=false;return route.abort('failed');}return route.fulfill({json:{success:true,data:{request_id:'1'}}});}
   if(path.endsWith('/reprint')){posts.push(route.request().postDataJSON());return route.fulfill({json:{success:true,data:{request_id:'2'}}});}
   return route.fulfill({contentType:'text/html',body:fs.readFileSync(nodePath.join(fixtureDir,'product-labels-fr.html'),'utf8')});
  });
  await page.goto('https://labels.test/kitchen/product-labels');await page.locator('[data-add-button]').waitFor({state:'visible'});await page.waitForFunction(()=>!document.querySelector('[data-add-button]').disabled);
  await page.selectOption('[data-portion]','20');await page.fill('[data-count]','2');await page.click('[data-add-button]');await page.click('[data-send]');await page.waitForFunction(()=>!document.querySelector('[data-send]').disabled);
  assert.equal(await page.locator('[data-lines] tr').count(),1);checks++;
  await page.click('[data-send]');await page.waitForFunction(()=>document.querySelectorAll('[data-lines] tr').length===0);assert.equal(posts[0].request_id,posts[1].request_id);assert.equal(stored.size,1);assert.equal(posts[1].lines[0].portion_id,20);assert.equal(posts[1].lines[0].count,2);checks+=4;
  assert.equal(await page.evaluate(()=>window.labelXss),undefined);assert.equal(errors.length,0);checks+=2;
  await page.locator('[data-jobs] button').click();await page.waitForFunction(()=>document.querySelector('[data-notice]').textContent.includes('file'));assert.ok(posts.some(p=>p.job_id==='1'));checks++;
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));checks++;
  if(width===1366)await page.screenshot({path:nodePath.join(fixtureDir,'product-labels-kitchen-1366.png'),fullPage:true});
  await page.close();
 }
 const page=await browser.newPage({viewport:{width:1366,height:768}});let device=10,auto=false,fail=false,writes=[];const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route('https://panel.test/**',async route=>{const path=new URL(route.request().url()).pathname;if(path.endsWith('/product-label-printer.js'))return route.fulfill({contentType:'application/javascript',body:fs.readFileSync(nodePath.join(panelRoot,'public/assets/js/product-label-printer.js'),'utf8')});if(path.includes('/ajax/')){if(route.request().method()==='PUT'){const body=route.request().postDataJSON();writes.push(body);if(fail)return route.fulfill({status:503,json:{success:false}});device=body.device_id;auto=body.auto_receipts;}return route.fulfill({json:{success:true,data:{device_id:device,auto_receipts:auto,devices:[{id:10,name:'POS A',type:'pos'},{id:11,name:'POS B',type:'pos'}]}}});}return route.fulfill({contentType:'text/html',body:fs.readFileSync(nodePath.join(fixtureDir,'label-printer-fr.html'),'utf8')});});
 await page.goto('https://panel.test/panel/shops/product-label-printer');await page.waitForFunction(()=>!document.querySelector('[type=submit]').disabled);await page.selectOption('#lp-device','11');await page.check('#lp-auto');await page.click('[type=submit]');await page.waitForFunction(()=>!document.querySelector('[type=submit]').disabled);assert.equal(device,11);assert.equal(auto,true);assert.equal(writes[0]._csrf,'fixture-token');checks+=3;
 await page.selectOption('#lp-device','');await page.click('[type=submit]');await page.waitForFunction(()=>!document.querySelector('[type=submit]').disabled);assert.equal(device,null);checks++;
 await page.selectOption('#lp-device','11');fail=true;await page.click('[type=submit]');await page.waitForFunction(()=>document.querySelector('[data-notice]').textContent.includes('Impossible'));assert.equal(await page.inputValue('#lp-device'),'11');assert.equal(errors.length,0);checks+=2;
 await page.screenshot({path:nodePath.join(fixtureDir,'product-labels-panel-1366.png'),fullPage:true});await page.close();console.log(`PASS: ${checks} browser checks; Kitchen 1366/768/390px, ambiguous response retry, portions, safe text, reprint; Panel device selection, pause and errors`);
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1);});
