const {chromium}=require('playwright');
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const dir=process.env.KITCHEN_DAY_FIXTURE_DIR||path.join(require('node:os').tmpdir(),'kitchen-day-mvp'),root=path.resolve(__dirname,'..');
const fixture=fs.readFileSync(path.join(dir,'kitchen-runtime-day.html'),'utf8'),seed=JSON.parse(fixture.match(/<script id="pd-seed"[^>]*>([\s\S]*?)<\/script>/)[1]);
(async()=>{const browser=await chromium.launch({headless:true,executablePath:process.env.CHROMIUM_PATH||'/usr/bin/chromium',args:['--no-sandbox']});let checks=0;
try{for(const width of [1024,768,390]){
const sheet=structuredClone(seed);Object.assign(sheet,{wizard_version:2,status:'OPEN',default_view:'stock',read_only:false,late_closing:false,scope_conflict:false,opening_blocker:null,stock_check:null,production_issue:null,overview_only:false,preparation:{target_date:'2026-10-30',lines:[]}});
const base=sheet.products[0];sheet.products=[
{...base,id_product:101,name:'Alpha',stock_quantity:'2',in_stock:'2',sector_id:1,sector_name:'Bakery',category_id:10,category_name:'Bread',category_groups:[{id:1,name:'Baked'}],shelf_life_category:'SSL',is_prepared_before_sales:true},
{...base,id_product:102,name:'Beta',stock_quantity:'3',in_stock:'3',sector_id:1,sector_name:'Bakery',category_id:20,category_name:'Cake',category_groups:[{id:2,name:'Sweet'}],shelf_life_category:'MSL',is_prepared_before_sales:true},
{...base,id_product:103,name:'Gamma',stock_quantity:'4',in_stock:'4',sector_id:1,sector_name:'Bakery',category_id:10,category_name:'Bread',category_groups:[{id:1,name:'Baked'}],shelf_life_category:'LSL'},
{...base,id_product:104,name:'Zero',stock_quantity:'0',in_stock:'0',sector_id:null,category_id:null,category_name:null,category_groups:[]}
];
const errors=[],posts=[];const page=await browser.newPage({viewport:{width,height:1000},isMobile:width<1000,hasTouch:width<1000});page.on('pageerror',e=>errors.push(e.message));
await page.route('**/*',async route=>{const req=route.request(),url=new URL(req.url());
if(url.pathname.startsWith('/kitchen/assets/')){const f=path.join(root,'public',url.pathname.slice('/kitchen/'.length));return route.fulfill({contentType:f.endsWith('.js')?'application/javascript':'text/css',body:fs.existsSync(f)?fs.readFileSync(f):''});}
if(req.method()==='POST'){posts.push(req.url());return route.fulfill({json:{success:true}});}
if(url.pathname.startsWith('/kitchen/ajax/'))return route.fulfill({json:{success:true,total:0,count:0}});
return route.fulfill({contentType:'text/html',body:fixture.replace('data-view="opening"','data-view="stock"').replace(/(<script id="pd-seed"[^>]*>)[\s\S]*?(<\/script>)/,(_,a,b)=>a+JSON.stringify(sheet)+b)});
});
await page.goto('http://kitchen.test/kitchen/production-day');const ids=()=>page.locator('[data-stock-product]').evaluateAll(ns=>ns.map(n=>Number(n.dataset.stockProduct)));assert.deepEqual(await ids(),[101,103,102]);checks++;
async function filter(key,value){const b=page.locator('[data-stock-filter="'+key+'"][data-filter-value="'+value+'"]');if(!await b.isVisible())await page.locator('[data-filter-disclosure="'+key+'"] summary').click();await b.click();}
await filter('group','1');assert.deepEqual(await ids(),[101,103]);assert.equal(await page.locator('[data-stock-filter="category"][data-filter-value="20"]').count(),0);await filter('category','10');await filter('life','SSL');assert.deepEqual(await ids(),[101]);checks+=3;
await filter('life','MSL');assert.deepEqual(await ids(),[]);assert.equal(await page.locator('.pd-filter-empty').isVisible(),true);checks++;
await filter('group','2');assert.equal(await page.locator('[data-stock-filter="category"][data-filter-value=""]').getAttribute('aria-pressed'),'true');assert.deepEqual(await ids(),[102]);checks++;
await filter('life','');await filter('group','');await page.locator('[data-stock-sort="name"]').click();assert.deepEqual(await ids(),[101,102,103]);await page.locator('[data-stock-direction]').click();assert.deepEqual(await ids(),[103,102,101]);await page.locator('[data-stock-sort="life"]').click();assert.deepEqual(await ids(),[103,102,101]);await page.locator('[data-stock-direction]').click();assert.deepEqual(await ids(),[101,102,103]);checks+=4;
await filter('category','10');await page.locator('[data-stock-product="103"] button').click();assert.ok((await page.locator('dialog').innerText()).includes('Gamma'),'Modification uses correct product after sort/filter');await page.locator('#pd-dialog-cancel').click();checks++;
await page.locator('[data-wizard-step="4"]').click();assert.equal(await page.locator('[data-preparation-product]').count(),2);const background=await page.locator('#pd-body').evaluate(n=>getComputedStyle(n).backgroundColor);assert.equal(background,'rgb(238, 244, 250)');assert.equal(await page.locator('#productionSectorButton').evaluate(n=>getComputedStyle(n).borderRadius),'10px');assert.equal(await page.locator('#tabletWorkerStatus .app-shift-status').evaluate(n=>getComputedStyle(n).borderRadius),'10px');checks+=4;
await page.screenshot({path:path.join(dir,'tomorrow-style-'+width+'.png')});await page.locator('[data-wizard-step="3"]').click();assert.deepEqual(await ids(),[101,103]);assert.notEqual(await page.locator('#pd-body').evaluate(n=>getComputedStyle(n).backgroundColor),background);assert.equal(posts.length,0,'Filtering and sorting never writes stock');assert.deepEqual(errors,[]);assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);checks+=5;
await page.screenshot({path:path.join(dir,'current-stock-filters-'+width+'.png')});await page.close();}
console.log('Stock filters browser: '+checks+' checks PASS (1024/768/390px, combined filters, sort, correct product action, independent preparation colours and tablet selectors).');}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1)});
