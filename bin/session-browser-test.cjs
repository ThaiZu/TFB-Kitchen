// Device auth recovery is separate from employee PIN and never repeats writes.
const {chromium}=require('playwright'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:'/usr/bin/chromium',args:['--no-sandbox']});let checks=0;
 try{
  for(const scenario of ['healthy','unavailable','expired','shift','legacy']){
   const page=await browser.newPage();let pings=0,writes=0;const errors=[];
   page.on('pageerror',e=>errors.push(e.message));
   await page.clock.install();
   await page.route('**/*',route=>{const req=route.request(),p=new URL(req.url()).pathname;
    if(p.endsWith('/kitchen-session.js'))return route.fulfill({contentType:'application/javascript',body:fs.readFileSync(path.join(__dirname,'../public/assets/js/kitchen-session.js'),'utf8')});
    if(p.endsWith('/ajax/session')){pings++;assert.equal(req.headers()['x-requested-with'],'XMLHttpRequest');return scenario==='unavailable'?route.fulfill({status:503,headers:{'X-Kitchen-Auth':'unavailable'},json:{success:false}}):route.fulfill({json:{success:true}});}
    if(p.endsWith('/ajax/change')){writes++;assert.equal(req.postData(),'fixture-write');
     if(scenario==='expired')return route.fulfill({status:401,headers:{'X-Kitchen-Auth':'required'},json:{success:false}});
     if(scenario==='shift')return route.fulfill({status:403,json:{success:false,description:'KITCHEN_SHIFT_REQUIRED'}});
     if(scenario==='unavailable')return route.fulfill({status:503,headers:{'X-Kitchen-Auth':'unavailable'},json:{success:false}});
     if(scenario==='legacy')return route.fulfill({status:302,headers:{Location:'/kitchen/auth'}});
     return route.fulfill({json:{success:true}});
    }
    if(p.endsWith('/auth'))return route.fulfill({contentType:'text/html',body:'<h1>Login</h1>'});
    return route.fulfill({contentType:'text/html',body:'<script src="/kitchen/assets/js/kitchen-session.js" data-root="http://kitchen.test/kitchen" defer></script>'});
   });
   await page.goto('http://kitchen.test/kitchen/knowledge/products');
   await page.waitForFunction(()=>window.__kitchenSession);assert.equal(pings,1);checks++;
   if(scenario==='healthy'){
    await page.clock.fastForward(300001);await page.waitForTimeout(50);assert.equal(pings,2,'Heartbeat renews idle visible tablet');
    await page.clock.fastForward(30000);await page.evaluate(()=>window.dispatchEvent(new Event('focus')));await page.waitForTimeout(50);assert.equal(pings,3,'Returning to tab checks device session');checks+=2;
   }
   if(scenario==='unavailable'){
    await page.clock.fastForward(30001);await page.waitForTimeout(50);assert.equal(pings,2,'Temporary outage gets a read-only heartbeat retry');checks++;
   }
   await page.evaluate(()=>{window.result=fetch('/kitchen/ajax/change',{method:'POST',body:'fixture-write'}).then(r=>r.json()).then(j=>window.response=j).catch(e=>window.requestError=e.name);});
   if(['expired','legacy'].includes(scenario))await page.waitForURL('**/kitchen/auth');
   else {await page.waitForFunction(()=>window.response);assert.ok(page.url().endsWith('/knowledge/products'));checks++;}
   assert.equal(writes,1,'Never replay inventory mutations');assert.deepEqual(errors,[]);checks+=2;
   await page.close();
  }
  console.log(`Kitchen session browser: ${checks} checks PASS`);
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
