const {chromium}=require('playwright'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const dir=process.env.KITCHEN_DAY_FIXTURE_DIR||'/workspace/work/kitchen-layout',root=path.resolve(__dirname,'..'),html=fs.readFileSync(path.join(dir,'kitchen-runtime-day.html'),'utf8'),seed=JSON.parse(html.match(/<script id="pd-seed"[^>]*>([\s\S]*?)<\/script>/)[1]);
(async()=>{const browser=await chromium.launch({headless:true,executablePath:'/usr/bin/chromium',args:['--no-sandbox']});let checks=0;
try{for(const width of [390,768,1024]){
 const page=await browser.newPage({viewport:{width,height:1000}}),errors=[];let starts=0,documents=0,selected=false,inventoryWrites=0;const sheet={...seed,wizard_version:2,status:'NOT_OPEN',read_only:false,opening_blocker:null,stock_check:null,production_issue:null,late_closing:false,overview_only:false};page.on('pageerror',e=>errors.push(e.message));
 await page.route('**/*',route=>{const req=route.request(),p=new URL(req.url()).pathname;
  if(p.includes('/assets/')){const file=path.join(root,'public',p.slice(p.indexOf('/assets/')+1));return route.fulfill({contentType:file.endsWith('.js')?'application/javascript':'text/css',body:fs.existsSync(file)?fs.readFileSync(file):''});}
  if(p.endsWith('/tablet-worker/roster'))return route.fulfill({json:{success:true,employees:[{id:24,name:'Clock worker',initials:'CW'}]}});
  if(p.endsWith('/tablet-worker/start')){starts++;if(starts>1)selected=true;return starts===1?route.fulfill({status:503,json:{success:false,message:'shop_clock_unavailable'}}):route.fulfill({json:{success:true}});}
  if(req.method()==='POST')inventoryWrites++;
  if(p.includes('/ajax/'))return route.fulfill({json:{success:true,count:0,total:0,items:[]}});
  documents++;return route.fulfill({contentType:'text/html',body:html.replace(/data-shift="[01]"/,'data-shift="'+(selected?'1':'0')+'"').replace(/(<script id="pd-seed"[^>]*>)[\s\S]*?(<\/script>)/,(_,a,b)=>a+JSON.stringify(sheet)+b)});
 });
 await page.goto('http://localhost/kitchen/production-day');
 const notice=page.locator('#pd-worker-required');assert.equal(await notice.isVisible(),true);assert.ok((await notice.innerText()).includes('Aby otworzyć dzień, wybierz pracownika'));assert.equal(await page.locator('#pd-wizard-next').isDisabled(),true);checks+=3;
 const action=notice.locator('[data-shift-action="login"]'),bounds=await action.boundingBox();assert.ok(bounds.height>=44&&bounds.x>=0&&bounds.x+bounds.width<=width);assert.ok((await notice.boundingBox()).y<(await page.locator('#pd-body').boundingBox()).y);checks+=2;
 await page.screenshot({path:path.join(dir,'production-worker-required-'+width+'.png')});await action.click();await page.locator('[data-tablet-worker-id="24"]').click();
 const boxes=page.locator('#tabletWorkerPinBoxes input');for(let i=0;i<4;i++)await boxes.nth(i).fill(String(i+1));
 await page.locator('#tabletWorkerStart').click();await page.waitForFunction(()=>!document.getElementById('tabletWorkerStart').disabled);
 const message=await page.locator('#tabletWorkerError').innerText();assert.ok(message.length>0&&!message.includes('shop_clock_unavailable'),'Clock outage shows localized UI text');assert.equal(starts,1);assert.equal(await notice.isVisible(),true);assert.equal(await page.locator('#pd-wizard-next').isDisabled(),true);checks+=4;
 await page.locator('#tabletWorkerStart').click();await page.waitForFunction(()=>document.getElementById('production-day').dataset.shift==='1'&&!document.getElementById('tabletWorkerModal').classList.contains('show'));assert.equal(starts,2);assert.ok(documents>=2,'Successful retry reloads the server-rendered worker/production context');assert.deepEqual(errors,[]);assert.equal(await notice.isVisible(),false);assert.equal(await page.locator('#pd-wizard-next').isDisabled(),false);assert.equal(inventoryWrites,0,'Selecting a worker never opens the production day automatically');checks+=6;await page.close();
}
console.log('Worker date browser: '+checks+' checks PASS (tablet/mobile PIN selection, readable clock failure and retry reload).');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1)});
