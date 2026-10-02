const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  const fixture = fs.readFileSync('/tmp/kitchen-waste-fixture.html', 'utf8');
  let checks = 0;
  const check = (value, label) => { checks++; if (!value) throw Error(label); };
  for (const width of [390, 1440]) {
    const browser = await chromium.launch({headless:true,args:['--no-sandbox']});
    const context = await browser.newContext({viewport:{width,height:900}});
    const page = await context.newPage();
    const writes = [];
    let conflict = false;
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.route('https://kitchen.test/**', async route => {
      const url = new URL(route.request().url());
      if (url.pathname.endsWith('/inventory/waste-data')) {
        await route.fulfill({contentType:'application/json',body:JSON.stringify({success:true,data:{lines:[{id_product:1,name:'Bread',in_stock:'9.0000',inventory_initialized_at:'2026-10-01',last_movement_id:'50',is_piece_based:1,is_divisible:0}],reasons:[{key:'expiration',label:'Expired',comment_required:false},{key:'other_waste',label:'Other',comment_required:true}],history:[],today:'2026-10-02',has_shift:true,available:true,reasons_available:true,history_available:true}})}); return;
      }
      if (url.pathname.endsWith('/inventory/waste')) {
        const payload = route.request().postDataJSON(); writes.push(payload);
        if (conflict) { await route.fulfill({status:409,contentType:'application/json',body:JSON.stringify({success:false,reason:'conflict',errors:{current_state:{in_stock:'9.0000'}}})}); return; }
        await route.fulfill({contentType:'application/json',body:JSON.stringify({success:true,data:{report_id:1,items:[{id_product:1,stock_after:'9.0000',reservation_warning:true,reservation_shortfall_quantity:'2.0000'}]}})}); return;
      }
      await route.fulfill({contentType:'text/html',body:fixture});
    });
    await page.goto('https://kitchen.test/kitchen/inventory?tab=waste');
    check(await page.locator('.wp-product').count()===1,'Only positive initialized supported product visible '+width);
    await page.locator('#wpShowAll').check(); check(await page.locator('.wp-product').count()===3,'Show all reveals unsupported and uninitialized '+width);
    check(await page.locator('.wp-product').nth(1).locator('button').isDisabled(),'Unsupported product blocked '+width);
    await page.locator('.wp-product').first().locator('button').click();
    await page.locator('#wpQuantity').fill('3'); await page.locator('#wpReason').selectOption('other_waste');
    await page.locator('#wpProductForm button[type="submit"]').click();
    check(await page.locator('#wpFormError').isVisible(),'Other waste note required '+width);
    await page.locator('#wpComment').fill('Dropped'); await page.locator('#wpProductForm button[type="submit"]').click();
    await page.locator('#wpSave').click(); await page.waitForFunction(() => document.querySelector('#wpNotice').textContent.includes('zapisane') || document.querySelector('#wpNotice').textContent.includes('saved'));
    check(writes.length===1 && writes[0].lines[0].reason==='other_waste' && writes[0].lines[0].comment==='Dropped','Whole report submitted atomically '+width);
    check(writes[0].idempotency_key.length>=16,'Stable report idempotency key '+width);
    check(await page.locator('#wpCart').textContent().then(t=>!t.includes('Bread')),'Draft cleared only after success '+width);
    conflict=true;
    await page.locator('.wp-product').first().locator('button').click(); await page.locator('#wpQuantity').fill('1'); await page.locator('#wpProductForm button[type="submit"]').click(); await page.locator('#wpSave').click(); await page.waitForFunction(()=>document.querySelector('#wpNotice').textContent.includes('Stan zmienił') || document.querySelector('#wpNotice').textContent.includes('Stock changed'));
    check(await page.locator('#wpCart').textContent().then(t=>t.includes('Bread')),'Conflict keeps draft '+width);
    check(writes.length===2,'Conflict does not automatically resend '+width);
    check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'No horizontal overflow '+width);
    check(errors.length===0,'No browser errors '+width);
    await context.close(); await browser.close();
  }
  console.log('Kitchen waste browser: '+checks+' checks passed');
})().catch(error=>{console.error(error);process.exit(1)});
