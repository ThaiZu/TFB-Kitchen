const {chromium}=require('playwright'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const dir=process.env.KITCHEN_DAY_FIXTURE_DIR||'/workspace/work/kitchen-layout',root=path.resolve(__dirname,'..'),fixture=fs.readFileSync(path.join(dir,'kitchen-product-cards.html'),'utf8');
(async()=>{const browser=await chromium.launch({headless:true,executablePath:'/usr/bin/chromium',args:['--no-sandbox']});let checks=0;
try{for(const width of [1440,1280,1024,768,390]){
 const page=await browser.newPage({viewport:{width,height:1000},isMobile:width<1000||width===1280,hasTouch:width<1000||width===1280}),errors=[];let posts=0;page.on('pageerror',e=>errors.push(e.message));
 await page.route('**/*',route=>{const req=route.request(),p=new URL(req.url()).pathname;if(req.method()==='POST')posts++;
  if(p.includes('/assets/')){const f=path.join(root,'public',p.slice(p.indexOf('/assets/')+1));return route.fulfill({contentType:p.endsWith('.js')?'application/javascript':'text/css',body:fs.existsSync(f)?fs.readFileSync(f):''});}
  if(p.includes('/ajax/'))return route.fulfill({json:{success:true,total:0,count:0,items:[]}});
  if(p.endsWith('main.jpg'))return p.includes('/101/')?route.fulfill({contentType:'image/png',body:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j0fcAAAAASUVORK5CYII=','base64')}):route.abort();
  return route.fulfill({contentType:'text/html',body:fixture});
 });
 await page.goto('http://kitchen.test/kitchen/knowledge/products');
 const ids=()=>page.locator('.product-item:not([hidden])').evaluateAll(nodes=>nodes.map(n=>Number(n.dataset.productId)));
 const choose=async(key,value)=>{
  if(key==='life'){
   const active=page.locator('[data-card-filter="life"][aria-pressed="true"]');
   if(value===''){if(await active.count())await active.click();return;}
   const button=page.locator('[data-card-filter="life"][data-filter-value="'+value+'"]');if(await button.getAttribute('aria-pressed')!=='true')await button.click();return;
  }
  const disclosure=page.locator('[data-card-disclosure="'+key+'"]');if(!await disclosure.evaluate(n=>n.open))await disclosure.locator('summary').click();
  assert.equal(await page.locator('[data-card-disclosure][open]').count(),1);
  await page.locator('[data-card-filter="'+key+'"][data-filter-value="'+value+'"]').click();assert.equal(await disclosure.evaluate(n=>n.open),false);
 };
 assert.equal(await page.locator('[data-card-disclosure][open]').count(),0);assert.deepEqual(await ids(),[101,102,105,106]);assert.equal(await page.locator('[data-card-filter="life"][data-filter-value="SSL"]').getAttribute('aria-pressed'),'true');assert.equal(await page.locator('.kp-chip,.kp-meta,.kp-photo-flags,.kp-foot').count(),0);checks+=3;
 await choose('sector','1');assert.deepEqual(await ids(),[101,102]);assert.deepEqual(await page.locator('[data-card-options=group] button').evaluateAll(nodes=>nodes.map(n=>n.dataset.filterValue)),['','1','2']);assert.deepEqual(await page.locator('[data-card-options=category] button').evaluateAll(nodes=>nodes.map(n=>n.dataset.filterValue)),['','11','10']);checks+=2;await choose('group','2');assert.deepEqual(await page.locator('[data-card-options=category] button').evaluateAll(nodes=>nodes.map(n=>n.dataset.filterValue)),['','10']);checks++;assert.deepEqual(await ids(),[101]);await choose('category','10');assert.deepEqual(await ids(),[101]);assert.equal(await page.locator('[data-card-filter=life][data-filter-value=MSL]').isVisible(),false,'Shelf-life choice without section products is hidden');checks+=5;
 await choose('sector','2');assert.equal(await page.locator('[data-card-filter="group"][aria-pressed=true]').getAttribute('data-filter-value'),'','Changing section clears incompatible category');assert.equal(await page.locator('[data-card-filter="category"][aria-pressed=true]').getAttribute('data-filter-value'),'','Changing section clears incompatible subcategory');assert.deepEqual(await page.locator('[data-card-options=group] button').evaluateAll(nodes=>nodes.map(n=>n.dataset.filterValue)),['','3']);assert.deepEqual(await ids(),[106]);assert.deepEqual(await page.locator('[data-card-options=category] button').evaluateAll(nodes=>nodes.map(n=>n.dataset.filterValue)),['','40']);await choose('life','MSL');assert.deepEqual(await ids(),[103]);await choose('group','3');checks+=4;await choose('category','20');assert.deepEqual(await ids(),[103]);await choose('life','');assert.deepEqual(await ids(),[103]);checks+=4;
 await choose('group','');await choose('sector','none');assert.deepEqual(await ids(),[104,105]);await page.locator('#productSearch').fill('juice');assert.deepEqual(await ids(),[104]);assert.equal(await page.locator('[data-card-options=category] button').count(),2);checks++;await page.locator('#productSearch').fill('does not exist');assert.equal(await page.locator('#noResults').isVisible(),true);await page.locator('#productSearch').fill('');await choose('sector','');await choose('life','SSL');checks+=3;
 const card=page.locator('.product-item:not([hidden]) .kp-card').first(),photo=card.locator('.kp-photo');const cardBox=await card.boundingBox(),photoBox=await photo.boundingBox();assert.ok(Math.abs(photoBox.width/cardBox.width-1/3)<.02,'Photo occupies one third of card');assert.equal(await card.evaluate(n=>getComputedStyle(n).borderRadius),'10px');assert.equal(await page.locator('#productionSectorButton').evaluate(n=>getComputedStyle(n).borderRadius),'10px');assert.equal(await page.locator('#tabletWorkerStatus .app-shift-status').evaluate(n=>getComputedStyle(n).borderRadius),'10px');checks+=4;
 await page.waitForFunction(()=>document.querySelector('.kp-photo img')?.naturalWidth>0);assert.equal(await photo.locator('img').isVisible(),true);await choose('life','LSL');await page.waitForFunction(()=>document.querySelector('[data-product-id="104"] img')?.hidden===true);assert.equal(await page.locator('[data-product-id="104"] .kp-photo span').isVisible(),true,'Broken photo keeps a compact placeholder');await choose('life','SSL');assert.equal(await page.locator('#productCount').innerText(),'4 produktów');assert.equal(posts,0);assert.deepEqual(errors,[]);assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
 assert.ok((await page.locator('.kp-filters').boundingBox()).height<=(width<=480?205:140),'Filters remain compact');
 await page.locator('[data-card-disclosure="sector"] summary').click();await page.locator('[data-card-disclosure="group"] summary').click();assert.equal(await page.locator('[data-card-disclosure][open]').count(),1);await page.keyboard.press('Escape');assert.equal(await page.locator('[data-card-disclosure][open]').count(),0);assert.equal(await page.locator('[data-card-disclosure="group"] summary').evaluate(n=>n===document.activeElement),true);
 await page.locator('[data-card-disclosure="category"] summary').click();await page.locator('#productSearch').click();assert.equal(await page.locator('[data-card-disclosure][open]').count(),0);checks+=5;
 await page.locator('[data-card-filter="life"][data-filter-value="SSL"]').click();assert.deepEqual(await ids(),[101,102,103,104,105,106]);await choose('life','SSL');checks++;
 if(width<1200||width===1280){const boxes=await page.locator('.product-item:not([hidden])').evaluateAll(nodes=>nodes.slice(0,2).map(n=>{const r=n.getBoundingClientRect();return {x:r.x,y:r.y};}));assert.ok(Math.abs(boxes[0].y-boxes[1].y)<2&&boxes[1].x>boxes[0].x);checks++;}
 await choose('sector','1');await page.locator('#kpFilterClear').click();assert.deepEqual(await ids(),[101,102,103,104,105,106]);assert.equal(await page.locator('#kpFilterClear').isVisible(),false);await choose('life','SSL');checks+=2;
 await page.locator('#productSearch').fill('does not exist');assert.equal(await page.locator('#kpShowAll').isVisible(),true);await page.locator('#kpShowAll').click();assert.deepEqual(await ids(),[101,102,103,104,105,106]);await choose('life','SSL');checks+=2;await page.screenshot({path:path.join(dir,'product-cards-'+width+'.png')});checks+=5;
 const listImage=await page.locator('[data-product-id="101"] img').getAttribute('src');await page.route('**/knowledge/products/101',route=>route.fulfill({contentType:'text/html',body:fs.readFileSync(path.join(dir,'kitchen-product-detail.html'),'utf8')}));await page.locator('[data-product-id="101"] .kp-card').click();const hero=page.locator('.fiche-hero>img');assert.equal(await hero.getAttribute('src'),listImage);await hero.click();assert.equal(await page.locator('#ficheLightbox').evaluate(n=>n.classList.contains('active')),true);assert.equal(await page.locator('#ficheLightboxImg').getAttribute('src'),await hero.getAttribute('src'));await page.keyboard.press('Escape');assert.equal(await page.locator('#ficheLightbox').evaluate(n=>n.classList.contains('active')),false);checks+=4;
 // The exact same global controls are rectangular on non-production pages as well.
 for(const file of ['kitchen-runtime-navigation.html','kitchen-plan-ready.html']){const html=fs.readFileSync(path.join(dir,file),'utf8');await page.route('**/shell-check',route=>route.fulfill({contentType:'text/html',body:html}));await page.goto('http://kitchen.test/shell-check');assert.equal(await page.locator('#productionSectorButton').evaluate(n=>getComputedStyle(n).borderRadius),'10px');assert.equal(await page.locator('#tabletWorkerStatus .app-shift-status').evaluate(n=>getComputedStyle(n).borderRadius),'10px');checks+=2;}
 await page.close();
 }
 // Global tablet sector defaults the catalogue and narrows dependent options.
 const selected=await browser.newPage();let sectorPosts=0;
 await selected.route('**/*',route=>{const req=route.request(),p=new URL(req.url()).pathname;
  if(p.includes('/assets/')){const f=path.join(root,'public',p.slice(p.indexOf('/assets/')+1));return route.fulfill({contentType:p.endsWith('.js')?'application/javascript':'text/css',body:fs.existsSync(f)?fs.readFileSync(f):''});}
  if(p.endsWith('/ajax/production-sector')){if(req.method()==='POST')sectorPosts++;return route.fulfill({json:{success:true,selected:1,items:[{id:1,name:'Bakery'},{id:2,name:'Kitchen'}]}});}
  if(p.includes('/ajax/'))return route.fulfill({json:{success:true,items:[],count:0,total:0}});
  return route.fulfill({contentType:'text/html',body:fs.readFileSync(path.join(dir,'kitchen-product-cards-sector.html'),'utf8')});
 });
 await selected.goto('http://localhost/kitchen/knowledge/products');
 assert.deepEqual(await selected.locator('.product-item:not([hidden])').evaluateAll(nodes=>nodes.map(n=>Number(n.dataset.productId))),[101,102]);
 assert.equal(await selected.locator('[data-card-filter="sector"][aria-pressed=true]').getAttribute('data-filter-value'),'1');
 assert.deepEqual(await selected.locator('[data-card-options=group] button').evaluateAll(nodes=>nodes.map(n=>n.dataset.filterValue)),['','1','2']);
 assert.deepEqual(await selected.locator('[data-card-options=category] button').evaluateAll(nodes=>nodes.map(n=>n.dataset.filterValue)),['','11','10']);
 await selected.locator('#productionSectorModal').evaluate(node=>node.dispatchEvent(new Event('show.bs.modal')));
 await selected.locator('#productionSectorList button[data-sector-id="2"]').evaluate(node=>node.click());
 await selected.waitForFunction(()=>location.pathname==='/kitchen/knowledge/products'&&document.readyState==='complete');
 await selected.waitForTimeout(100);assert.equal(sectorPosts,1);assert.ok(selected.url().endsWith('/kitchen/knowledge/products'));checks+=6;
 await selected.close();
 console.log('Kitchen product cards: '+checks+' checks PASS (desktop/tablet/touch/mobile layouts, combined filters, default SSL, photos/fallback, compact cards and global selector corners).');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1)});
