const {chromium}=require('playwright'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const root=path.resolve(__dirname,'..'),dir=process.env.KITCHEN_BAKING_FIXTURE_DIR||path.join(require('node:os').tmpdir(),'kitchen-baking');
(async()=>{const browser=await chromium.launch({headless:true,executablePath:'/usr/bin/chromium',args:['--no-sandbox']});let checks=0;
try{for(const width of [1920,1280,1024,768,390,320]){
 let fixture='pl',writes=0;const errors=[];const page=await browser.newPage({viewport:{width,height:1000},hasTouch:width<=1024});page.on('pageerror',error=>errors.push(error.message));
 await page.route('**/*',route=>{const req=route.request(),p=new URL(req.url()).pathname;if(req.method()==='POST')writes++;
 if(p.includes('/assets/')){const file=path.join(root,'public',p.slice(p.indexOf('/assets/')+1));return route.fulfill({contentType:p.endsWith('.js')?'application/javascript':p.endsWith('.css')?'text/css':'application/octet-stream',body:fs.existsSync(file)?fs.readFileSync(file):''});}
 if(p.endsWith('.jpg'))return route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="400" height="200"><rect width="400" height="200" fill="#f6dcac"/><circle cx="200" cy="100" r="90" fill="#c78a42"/></svg>'});
 if(p.includes('/ajax/'))return route.fulfill({json:{success:true,count:0,total:0,items:[]}});
 return route.fulfill({contentType:'text/html',body:fs.readFileSync(path.join(dir,'baking-'+fixture+'.html'))});});
 await page.goto('http://kitchen.test/kitchen/knowledge/products/101');
 await page.screenshot({path:path.join(dir,'general-'+width+'.png')});
 const photo=page.locator('.fiche-hero>img');await photo.waitFor();const box=await page.locator('.fiche-hero').boundingBox();assert.ok(Math.abs(box.width-box.height)<2,'Square hero on every viewport');assert.equal(await photo.evaluate(node=>getComputedStyle(node).objectFit),'contain');checks+=2;
 assert.equal(await page.locator('#ficheGeneralPanel').isVisible(),true);assert.equal(await page.locator('#ficheBakingPanel').isVisible(),false);await photo.click();assert.equal(await page.locator('#ficheLightbox').isVisible(),true);await page.keyboard.press('Escape');checks+=3;
 await page.locator('#ficheBakingTab').click();assert.equal(await page.locator('#ficheGeneralPanel').isVisible(),false);assert.equal(await page.locator('[data-fiche-general]:visible').count(),0);assert.equal(await page.locator('[data-baking-stage]:visible').count(),3);assert.equal(await page.locator('[data-baking-local]:visible').count(),2);assert.equal(await page.locator('#ficheBakingTab').getAttribute('aria-selected'),'true');assert.ok((await page.locator('#ficheBakingPanel').innerText()).includes('180'));assert.ok((await page.locator('#ficheBakingPanel').innerText()).includes('190'));assert.equal(await page.locator('#ficheBakingPanel script').count(),0);assert.equal(await page.locator('#ficheBakingPanel input').count(),0);checks+=9;
 assert.match(await page.locator('[data-baking-program]').innerText(), /Main oven.*7/s);checks++;
 const instructions = page.locator('[data-baking-instructions]');
 assert.equal(await instructions.locator('[data-baking-note="notes_1"]').innerText(), 'Leave a gap\nKeep trays even');
 assert.equal(await instructions.locator('[data-baking-note="notes_2"]').innerText(), 'Check <script>example()</script>\nCool on rack');
 checks += 5;
 for (const kind of ['correct', 'incorrect']) {
    const buttons = instructions.locator(`[data-baking-gallery="${kind}"] button`);
    assert.equal(await buttons.count(), 2);
    await buttons.first().focus();
    await page.keyboard.press('Enter');
    assert.equal(await page.locator('#ficheLightbox').isVisible(), true);
    assert.match(await page.locator('#ficheLightboxImg').getAttribute('src'), new RegExp(`${kind}-1[.]jpg$`));
    await page.keyboard.press('Escape');
    assert.equal(await buttons.first().evaluate(node => node === document.activeElement), true);
    checks += 4;
 }
 await instructions.locator('h3').scrollIntoViewIfNeeded();
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
 await page.screenshot({path:path.join(dir,'baking-'+width+'.png'),fullPage:false});
 checks++;
 await page.locator('#ficheBakingTab').focus();await page.keyboard.press('ArrowLeft');assert.equal(await page.locator('#ficheGeneralPanel').isVisible(),true);assert.equal(await page.locator('#ficheGeneralTab').evaluate(node=>node===document.activeElement),true);await page.keyboard.press('End');assert.equal(await page.locator('#ficheBakingPanel').isVisible(),true);checks+=3;
 for(const state of ['empty','unavailable']){fixture=state;await page.goto('http://kitchen.test/kitchen/knowledge/products/101');await page.locator('#ficheBakingTab').click();assert.ok((await page.locator('#ficheBakingPanel').innerText()).trim().length>20);assert.equal(await page.locator('[data-baking-stage]').count(),0);await page.locator('#ficheGeneralTab').click();assert.equal(await page.locator('#ficheGeneralPanel').isVisible(),true);checks+=3;}
 fixture = 'partial';
 await page.goto('http://kitchen.test/kitchen/knowledge/products/101');
 await page.locator('#ficheBakingTab').click();
 assert.equal(await instructions.locator('[data-baking-note="notes_2"]').innerText(), 'Partial configuration\nWithout a shop program');
 assert.equal(await instructions.locator('[data-baking-pieces],[data-baking-gallery],[data-baking-note="notes_1"]').count(), 0);
 checks += 3;
 assert.equal(writes,0);assert.deepEqual(errors,[]);checks+=2;await page.close();
}console.log('Knowledge baking browser: '+checks+' checks PASS');}finally{await browser.close();}})().catch(error=>{console.error(error);process.exit(1)});
