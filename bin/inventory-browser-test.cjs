const { chromium }=require('playwright');const fs=require('fs');
(async()=>{
 const browser=await chromium.launch({headless:true,args:['--no-sandbox']});let checks=0;
 const check=(value,label)=>{checks++;if(!value)throw Error(label);};
 for(const width of [1440,390]){
  const context=await browser.newContext({viewport:{width,height:950}});const page=await context.newPage();let requests=[],fail=true;const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('https://kitchen.test/**',async route=>{
   const url=new URL(route.request().url());
   if(route.request().method()==='POST'){
    const data=route.request().postDataJSON();requests.push(data);
    if(data.id_product===3&&fail){await route.abort('failed');return;}
    if(data.id_product===2&&url.pathname.endsWith('/count')){await route.fulfill({status:409,contentType:'application/json',body:JSON.stringify({success:false,reason:'conflict'})});return;}
    await route.fulfill({contentType:'application/json',body:'{"success":true}'});return;
   }
   const fixture=url.pathname.includes('receive')?'/tmp/kitchen-receipt-fixture.html':url.pathname.includes('production-list')?'/tmp/tfb-production-iteration.html':url.pathname.includes('production')?'/tmp/tfb-production-saved.html':'/tmp/kitchen-inventory-fixture.html';
   await route.fulfill({contentType:'text/html',body:fs.readFileSync(fixture,'utf8')});
  });
  await page.goto('https://kitchen.test/kitchen/inventory');
  check(await page.locator('[data-product="1"]').isVisible(),'Nonzero visible '+width);
  check(!(await page.locator('[data-product="2"]').isVisible()),'Zero hidden '+width);
  check(!(await page.locator('[data-product="3"]').isVisible()),'Unknown hidden '+width);
  check(!(await page.locator('[data-product="5"]').isVisible()),'Unverified999 hidden '+width);
  check(await page.locator('#sbHiddenHint b').textContent()==='3','Hidden counter '+width);
  check(await page.locator('[data-product="3"] .sb-quantity').inputValue()==='','Unknown blank '+width);
  check(await page.locator('[data-product="4"] .sb-quantity').isDisabled(),'Unsupported disabled '+width);
  await page.locator('#sbSave').click();check(requests.length===0,'No unchecked save '+width);
  await page.locator('#sbSelectVisible').click();
  check(await page.locator('.sb-selected:checked').count()===1,'Confirm visible selects only confirmed nonzero '+width);
  await page.locator('#sbZeros').check();
  check(await page.locator('[data-product="2"]').isVisible()&&await page.locator('[data-product="3"]').isVisible()&&await page.locator('[data-product="5"]').isVisible(),'Switch shows full list '+width);
  await page.locator('[data-product="3"] .sb-selected').check();await page.locator('#sbSave').click();check(requests.length===0,'Empty unknown cannot be written as zero '+width);
  await page.locator('[data-product="3"] .sb-quantity').fill('0');
  await page.locator('#sbSave').click();await page.waitForFunction(()=>document.querySelector('[data-product="1"]').dataset.done==='1'&&!document.getElementById('sbSave').disabled);
  check(requests.length===2&&requests[1].in_stock==='0','Only counted rows submitted including explicit zero '+width);
  check(await page.locator('[data-product="1"] .sb-quantity').isDisabled(),'Success row disabled '+width);
  check(await page.locator('[data-product="3"] .sb-quantity').evaluate(e=>e.readOnly),'Uncertain row immutable '+width);
  const previous=requests[1];fail=false;
  await page.reload();await page.locator('#sbSave').click();await page.waitForFunction(()=>document.querySelector('[data-product="3"]').dataset.done==='1');
  check(requests.length===3&&requests[2].idempotency_key===previous.idempotency_key,'Reload retries same operation '+width);
  await page.locator('#sbZeros').check();await page.locator('[data-product="2"] .sb-quantity').fill('7');await page.locator('#sbSave').click();await page.waitForFunction(()=>document.querySelector('[data-product="2"]').dataset.blocked==='1');
  check(await page.locator('[data-product="2"] .sb-result').textContent().then(t=>t.includes('zmieniły')),'Conflict explains recount '+width);
  check(await page.locator('[data-product="2"] .sb-quantity').isDisabled(),'Conflict blocked until reload '+width);
  check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Inventory no page overflow '+width);
  requests=[];
  await page.goto('https://kitchen.test/kitchen/production/receive');
  check(await page.locator('[data-product="1"] .sb-quantity').inputValue()==='15','Remaining receipt prefilled '+width);
  check(await page.locator('[data-product="1"] .sb-selected').isChecked(),'Remaining preselected '+width);
  check(!(await page.locator('[data-product="2"] .sb-selected').isChecked()),'Completed not preselected '+width);
  await page.locator('[data-product="1"] .sb-quantity').fill('8');await page.locator('#sbSave').click();await page.waitForFunction(()=>document.querySelector('[data-product="1"]').dataset.done==='1');
  check(requests.length===1&&requests[0].quantity===8&&requests[0].expected_revision===3&&requests[0].expected_produced_quantity===10,'Receipt actor-independent concurrency payload '+width);
  check(await page.locator('[data-product="1"] .sb-selected').isDisabled(),'Receipt no duplicate second submission '+width);
  check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Receipt no page overflow '+width);
  await page.goto('https://kitchen.test/kitchen/production-list');check(await page.locator('#plRemaining').isChecked(),'Remaining default enabled '+width);
  await page.locator('tr[data-product="1"] .pl-plan-input').fill('0');check(!(await page.locator('tr[data-product="1"]').isVisible()),'Remaining filter actually runs '+width);
  await page.goto('https://kitchen.test/kitchen/production');await page.locator('input[type="date"]').fill('2026-09-15');await page.waitForURL('**/production?date=2026-09-15');check(true,'Date loads automatically '+width);
  check(errors.length===0,'No JS errors '+width);await context.close();
 }
 await browser.close();console.log('Kitchen inventory browser: '+checks+' checks passed');
})().catch(error=>{console.error(error);process.exit(1)});
