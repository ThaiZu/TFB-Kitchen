const {chromium}=require('playwright'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const fixtureDir=process.env.KITCHEN_DAY_FIXTURE_DIR||path.join(require('node:os').tmpdir(),'kitchen-day-mvp'),kitchen=path.resolve(__dirname,'..'),fixture=fs.readFileSync(path.join(fixtureDir,'kitchen-runtime-day.html'),'utf8'),seed=JSON.parse(fixture.match(/<script id="pd-seed"[^>]*>([\s\S]*?)<\/script>/)[1]);
(async()=>{const browser=await chromium.launch({headless:true,executablePath:'/usr/bin/chromium',args:['--no-sandbox']});let checks=0;
try{for(const width of [1024,768,390]){
 const today=seed.today,old=new Date(Date.parse(today+'T12:00:00Z')-86400000).toISOString().slice(0,10),writes=[],errors=[],reads=[];
 let closed=false,blocked=true,race=false,nonempty=false,failOnce=true,technicalOnly=false;
 function sheet(){const data={...structuredClone(seed),wizard_version:2,closing_wizard_version:1,preparation_revision:0,can_prepare:true,date:today,absence_reasons:['missing_ingredients','equipment_failure','not_ready','other'],read_only:false,status:'NOT_OPEN',revision:0,default_view:'opening',products:[]};
  if(technicalOnly){Object.assign(data,{status:'OPEN',default_view:'stock',production_issue:{canonical_status:'open',pending_events:1},closing_blocked:true,products:[{...seed.products.find(p=>p.id_product===6),stock_quantity:'1',in_stock:'1'}]});}
  else if(blocked&&!closed){Object.assign(data,{date:old,status:'OPEN',revision:1,default_view:'closing',late_closing:true,can_prepare:false,can_confirm_empty_close:!nonempty,closing_blocked:nonempty,production_issue:{canonical_status:'closed',pending_events:1},products:[{...seed.products.find(p=>p.id_product===6),stock_quantity:nonempty?'1':'0',in_stock:nonempty?'1':'0',default_opening:false}]});}
  return data;
 }
 const page=await browser.newPage({viewport:{width,height:1000},isMobile:width<1000,hasTouch:width<1000});page.on('pageerror',e=>errors.push(e.message));
 await page.route('**/*',route=>{const req=route.request(),url=new URL(req.url()),p=url.pathname;
  if(p.startsWith('/kitchen/assets/')){const f=path.join(kitchen,'public',p.slice('/kitchen/'.length));return route.fulfill({contentType:p.endsWith('.js')?'application/javascript':'text/css',body:fs.existsSync(f)?fs.readFileSync(f):''});}
  if(p.startsWith('/kitchen/ajax/'))return route.fulfill({json:{success:true,total:0,count:0}});
  if(p==='/kitchen/production-day/data'){reads.push(url);return route.fulfill({json:{success:true,data:sheet()}});}
  if(req.method()==='POST'){
   const input=req.postDataJSON(),op=p.split('/').pop();writes.push({op,input});
   if(op==='open'&&race){blocked=true;return route.fulfill({status:409,json:{success:false,description:'KITCHEN_DAY_PREVIOUS_OPEN'}});}
   assert.equal(op,'close');assert.equal(input.confirm_empty_day,true);assert.equal(input.date,old);assert.equal(input.lines.length,0);
   if(failOnce){failOnce=false;nonempty=true;return route.fulfill({status:409,json:{success:false,description:'KITCHEN_DAY_EMPTY_STOCK_CHANGED'}});}
   closed=true;return route.fulfill({json:{success:true,data:{status:'CLOSED',revision:2}}});
  }
  const data=sheet();return route.fulfill({contentType:'text/html',body:fixture.replace('data-view="opening"','data-view="'+data.default_view+'"').replace(/(<script id="pd-seed"[^>]*>)[\s\S]*?(<\/script>)/,(_,a,b)=>a+JSON.stringify(data).replace(/</g,'\\u003c')+b)});
 });
 // Active overdue day is selected immediately, with no date navigation or preparation escape.
 await page.goto('http://kitchen.test/kitchen/production-day?view=prepare');
 assert.equal(await page.locator('#pd-business-date').textContent(),old);assert.equal(await page.locator('#pd-business-date').getAttribute('datetime'),old);
 assert.equal(await page.locator('input[type=date], .pd-date-form').count(),0);assert.equal(await page.locator('[data-wizard-step="4"]').getAttribute('aria-current'),'step');
 for(const i of [0,1,2,3,5])assert.equal(await page.locator('[data-wizard-step="'+i+'"]').isDisabled(),true);
 assert.equal(await page.locator('[data-preparation-product], [data-opening-product]').count(),0);checks+=10;
 await page.screenshot({path:path.join(fixtureDir,'active-overdue-day-'+width+'.png')});
 await page.locator('#pd-footer').getByRole('button',{name:'Zakończ pusty dzień',exact:true}).click();assert.equal(await page.locator('#pd-dialog-title').innerText(),'Potwierdź pusty magazyn');assert.equal(writes.length,0);
 await page.locator('#pd-dialog-save').click();await page.waitForFunction(()=>document.getElementById('pd-feedback').textContent.includes('Stan magazynu zmienił'));
 assert.equal(closed,false);assert.equal(await page.locator('#pd-business-date').textContent(),old);assert.equal(await page.locator('#pd-footer button:enabled, #pd-close-start:enabled').count(),0);checks+=5;
 // Confirmed overdue close returns to the canonical page; no date is chosen in the browser.
 nonempty=false;await page.reload();await page.locator('#pd-footer button').click();await page.locator('#pd-dialog-save').click();await page.waitForURL(url=>url.pathname.endsWith('/production-day')&&url.search==='');
 await page.waitForFunction(date=>document.getElementById('pd-business-date').textContent===date,today);
 assert.equal(closed,true);assert.equal(await page.locator('#pd-wizard-next').isEnabled(),true);assert.equal(writes.filter(w=>w.op==='close').length,2);checks+=3;
 // A concurrent overdue day is resolved before the employee can continue the opening wizard.
 closed=false;blocked=false;race=true;await page.reload();await page.locator('#pd-wizard-next').click();await page.locator('#pd-wizard-next').click();await page.locator('#pd-wizard-next').click();
 await page.waitForFunction(date=>document.getElementById('pd-business-date').textContent===date,old);
 assert.equal(await page.locator('[data-wizard-step="4"]').getAttribute('aria-current'),'step');assert.equal(await page.locator('[data-wizard-step="5"]').isDisabled(),true);assert.equal(writes.at(-1).op,'open');assert.ok(reads.every(url=>!url.searchParams.has('date')));checks+=4;
 technicalOnly=true;await page.reload();assert.equal(await page.locator('.pd-recovery-notice').isVisible(),true);assert.match(await page.locator('.pd-recovery-notice').innerText(),/administrator/);assert.deepEqual(errors,[]);assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);checks+=4;await page.close();
 }
 console.log('Kitchen active-day recovery: '+checks+' checks PASS (3 layouts, overdue-only closing, static date, safe empty closing, return to today and concurrent blocker).');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1)});
