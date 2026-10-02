const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  const fixture = fs.readFileSync('/tmp/kitchen-carryover-fixture.html', 'utf8');
  let checks = 0;
  const check = (value, label) => { checks++; if (!value) throw Error(label); };
  for (const width of [390, 1440]) {
    const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
    const context = await browser.newContext({ viewport: { width, height: 900 } });
    const page = await context.newPage();
    const writes = [];
    const errors = [];
    let conflict = false;
    page.on('pageerror', error => errors.push(error.message));
    const sheet = () => ({
      success: true,
      data: {
        source_date: '2026-10-02', target_date: '2026-10-03', timezone: 'Europe/Brussels', has_shift: true, available: true,
        waste_reasons: [{ key: 'expiration', label: 'Expired', comment_required: false }, { key: 'other_waste', label: 'Other', comment_required: true }],
        items: [
          { id_product: 1, product_name: 'Bread', in_stock: conflict ? '8.0000' : '9.0000', last_movement_id: conflict ? '52' : '50', supported: true, status: 'needs_decision' },
          { id_product: 2, product_name: 'Flour', in_stock: '3.0000', last_movement_id: '42', supported: false, status: 'unsupported' },
          { id_product: 3, product_name: 'Bun', in_stock: '6.0000', last_movement_id: '45', supported: true, status: 'settled', settlement: { carryover_quantity: '6.0000', waste_quantity: '0.0000', state_changed_after_settlement: true } },
        ],
        from_previous_day: [{ id_product: 4, product_name: 'Croissant', carryover_quantity: '2.0000', source_date: '2026-10-01' }],
      },
    });
    await page.route('https://kitchen.test/**', async route => {
      const url = new URL(route.request().url());
      if (url.pathname.endsWith('/inventory/carryover-data')) return route.fulfill({ contentType: 'application/json', body: JSON.stringify(sheet()) });
      if (url.pathname.endsWith('/inventory/carryover')) {
        const payload = route.request().postDataJSON();
        writes.push(payload);
        if (conflict) return route.fulfill({ status: 409, contentType: 'application/json', body: JSON.stringify({ success: false, reason: 'conflict' }) });
        return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: { settlement_id: 1, items: [{ id_product: 1, reservation_warning: true, reservation_shortfall_quantity: '2.0000' }] } }) });
      }
      return route.fulfill({ contentType: 'text/html', body: fixture });
    });
    await page.goto('https://kitchen.test/kitchen/inventory?tab=tomorrow');
    check(await page.locator('.cp-product').count() === 3, 'Cards include pending, unsupported and settled ' + width);
    check(await page.locator('.cp-product').nth(1).textContent().then(text => text.includes('not supported') || text.includes('nie jest obsługiwany')), 'Unsupported product explained ' + width);
    await page.locator('#cpSave').click();
    check(writes.length === 0, 'Missing decisions are never submitted ' + width);
    const pending = page.locator('.cp-product').first();
    await pending.locator('input[type="number"]').nth(0).fill('6');
    await pending.locator('input[type="number"]').nth(1).fill('3');
    await pending.locator('select').selectOption('other_waste');
    await pending.locator('input[type="checkbox"]').check();
    await page.locator('#cpSave').click();
    check(writes.length === 0, 'other_waste without note is never submitted ' + width);
    await pending.locator('textarea').fill('Dropped');
    await page.locator('#cpSave').click();
    await page.waitForFunction(() => document.querySelector('#cpNotice').textContent.includes('zapisane') || document.querySelector('#cpNotice').textContent.includes('saved'));
    check(writes.length === 1 && writes[0].lines[0].carryover_quantity === '6' && writes[0].lines[0].waste_quantity === '3' && writes[0].lines[0].waste_reason === 'other_waste', 'One atomic settlement submitted ' + width);
    check(writes[0].idempotency_key.length >= 16 && !('id_employee' in writes[0]), 'Browser never supplies actor and uses a document key ' + width);
    conflict = true;
    const current = page.locator('.cp-product').first();
    await current.locator('button').filter({ hasText: /tomorrow|jutro/i }).click();
    await page.locator('#cpSave').click();
    await page.waitForFunction(() => document.querySelector('#cpNotice').textContent.includes('zmienił') || document.querySelector('#cpNotice').textContent.includes('changed'));
    check(writes.length === 2, 'Conflict is not automatically resent ' + width);
    check(await page.locator('.cp-product.cp-stale').count() >= 1, 'Conflict preserves draft and requires reassessment ' + width);
    check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'No horizontal overflow ' + width);
    check(errors.length === 0, 'No browser errors ' + width);
    await context.close();
    await browser.close();
  }
  console.log('Kitchen carryover settlement browser: ' + checks + ' checks passed');
})().catch(error => { console.error(error); process.exit(1); });
