<?php
/** Render the real controller/layout, including missing optional device config. */
require __DIR__ . '/../vendor/autoload.php';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/kitchen/dashboard';
foreach (['JWT_SECRET', 'JWT_ISSUER', 'JWT_ACCESS_TOKEN_EXPIRY', 'JWT_REFRESH_TOKEN_EXPIRY', 'DEFAULT_COUNTRY', 'CURRENCY', 'CURRENCY_SYMBOL', 'APP_NAME', 'APP_DESC'] as $key) {
    $_ENV[$key] = getenv($key) ?: '';
}
require __DIR__ . '/../config/app.php';
require __DIR__ . '/../src/core/Support/functions.php';
$container = require __DIR__ . '/../src/core/Container/Container.php';

use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\core\Support\DeviceMode;
use App\Kitchen\core\Support\GlobalRegistry;

$checks = 0;
$expect = static function (bool $condition, string $message) use (&$checks): void {
    ++$checks;
    if (!$condition) throw new RuntimeException($message);
};
GlobalRegistry::set('user', ['shop_id' => 2, 'device_id' => 7]);
DeviceMode::rules()->applyConfig(null);
$expect(DeviceMode::rules()->navKeys('production') === \App\Kitchen\app\Services\Me\DeviceModeService::DEFAULT_NAV['production'], 'Reproduce missing runtime device configuration');
$expect(DeviceMode::rules()->tabKeys('production') === \App\Kitchen\app\Services\Me\DeviceModeService::DEFAULT_TABS['production'], 'Reproduce empty runtime tabs');

foreach (['pl', 'en', 'fr', 'nl', 'it'] as $language) {
    GlobalRegistry::set('lang_code', $language);
    foreach (['production', 'gestion', 'webshop'] as $mode) {
        $_COOKIE[DeviceMode::COOKIE_MODE] = $mode;
        foreach (['dashboard/dashboard' => 'dashboard', 'production_list/saved' => 'production'] as $view => $path) {
            $_GET['url'] = $path;
            ob_start();
            // A page's generic `items` must never replace the navigation list.
            $container->get(Controller::class)->view($view, ['items' => [], 'saved_plan' => ['exists' => false], 'entry_employees'=>[['id'=>9,'name'=>'Test Employee','initials'=>'TE']]]);
            $html = ob_get_clean();
            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);
            $expect($xpath->query('//a[@href="'.ROOT.'/inventory" or @href="'.ROOT.'/production/today" or @href="'.ROOT.'/production-list"]')->length===0,'Retired views have no visible links');
            $themeHash = substr(hash_file('sha256', __DIR__ . '/../public/assets/css/atelier-theme.css'), 0, 16);
            $expect($xpath->query('//link[@href="' . ROOT . '/assets/css/atelier-theme.css?v=' . $themeHash . '"]')->length === 1, 'Stylesheet URL tracks its content');
            foreach (["//*[@id='appNavSheet']", "//nav[contains(@class,'app-tabbar-bar')]"] as $scope) {
                $destinations = ['dashboard', 'production-day', 'knowledge/products', 'checklists', 'orders'];
                $expect($xpath->query($scope.'//a[@href="'.ROOT.'/knowledge"]')->length===0,'Knowledge link removed from footer');
                if (str_contains($scope, 'appNavSheet')) $destinations[] = 'material-stocktaking';
                foreach ($destinations as $destination) {
                    $links = $xpath->query($scope . '//a[@href="' . ROOT . '/' . $destination . '"]');
                    $expect($links->length === 1, "$language / $mode / $path missing or duplicate $destination in $scope");
                    $expect(trim($links->item(0)->textContent) !== '', "Translated label for $destination");
                }
            }
            if ($language === 'pl' && $mode === 'production' && $path === 'dashboard') {
                file_put_contents((getenv('KITCHEN_DAY_FIXTURE_DIR')?:sys_get_temp_dir()) . '/kitchen-runtime-navigation.html', $html);
            }

            $_GET['url'] = 'production/today';
            ob_start();
            $container->get(Controller::class)->view('production_today/index', [
                'available' => true,
                'exists' => true,
                'date' => '2026-10-13',
                'id_plan' => '1',
                'can_confirm' => true,
                'cycles' => [[
                    'id_cycle' => '1',
                    'key' => 'c1',
                    'name' => 'Morning',
                    'time' => '06:00',
                    'end_time' => '10:00',
                    'is_current' => true,
                ]],
                'categories' => [[
                    'id_category' => 1,
                    'name' => 'Viennoiseries',
                    'lines' => [[
                        'id_product' => 1,
                        'name' => 'Croissant',
                        'quantities' => ['c1' => '12.0000'],
                        'total_quantity' => '12.0000',
                    ]],
                ]],
            ]);
            $todayHtml = ob_get_clean();
            $expect(str_contains($todayHtml, 'Croissant') && str_contains($todayHtml, '>12<') && str_contains($todayHtml, 'pt-current-cycle') && str_contains($todayHtml, 'data-production-cycle-confirm'), 'Today production view renders grouped active cycle confirmation: ' . $language);
        }
    }
}
$expect($container->get(\App\Kitchen\app\Http\Controllers\Inventory\InventoryController::class) instanceof \App\Kitchen\app\Http\Controllers\Inventory\InventoryController, 'Inventory controller resolves in production DI');
$expect($container->get(\App\Kitchen\app\Http\Controllers\Production\ProductionStockAdjustmentController::class) instanceof \App\Kitchen\app\Http\Controllers\Production\ProductionStockAdjustmentController, 'Production stock-adjustment controller resolves in production DI');
$expect($container->get(\App\Kitchen\app\Http\Controllers\Material\MaterialStocktakingController::class) instanceof \App\Kitchen\app\Http\Controllers\Material\MaterialStocktakingController, 'Material stocktaking controller resolves in DI');
$dispatcher=\FastRoute\simpleDispatcher(require __DIR__.'/../src/core/Bootstrap/routes.php');
foreach ([
    ['GET','/inventory'], ['POST','/inventory/count'],
    ['GET','/material-stocktaking'], ['POST','/ajax/material-stocktaking/preview'], ['POST','/ajax/material-stocktaking/save'],
    ['GET','/inventory/waste-data'], ['POST','/inventory/waste'],
    ['GET','/inventory/carryover-data'], ['POST','/inventory/carryover'],
    ['GET','/production/today'],
    ['POST','/ajax/production/today/cycles/confirm'],
    ['GET','/production/waste'], ['POST','/ajax/production/waste'],
    ['GET','/production/stock-correction'], ['POST','/ajax/production/stock-correction'],
    ['GET','/production/receive'],
] as [$method,$path]) {
    $expect($dispatcher->dispatch($method,$path)[0] === \FastRoute\Dispatcher::FOUND,'New route resolves '.$method.' '.$path);
}
// Render the selected department through the real controller and cookie context.
GlobalRegistry::set('user',['shop_id'=>2,'device_id'=>7]);
\App\Kitchen\core\Support\ProductionSector::save(2,1,'Bakery');
ob_start();$container->get(Controller::class)->view('dashboard/dashboard',['entry_employees'=>[]]);$selectedHtml=ob_get_clean();
$dom=new DOMDocument();@$dom->loadHTML($selectedHtml);$xpath=new DOMXPath($dom);
$expect(trim($xpath->query('//*[@id="productionSectorButton"]')->item(0)->textContent)==='Bakery','Selected department badge contains only the department name');
$expect($xpath->query('//*[@id="productionSectorList"]')->length===1 && $xpath->query('//*[@id="productionSectorModal"]//select')->length===0,'Department modal uses touch choices instead of a dropdown');
\App\Kitchen\core\Support\ProductionSector::save(2,null);
$fixtureDir=getenv('KITCHEN_DAY_FIXTURE_DIR')?:sys_get_temp_dir();
$dayFixture=$fixtureDir.'/ui.html';
if(is_file($dayFixture)){
 preg_match('/<script id="pd-seed"[^>]*>(.*?)<\/script>/s',file_get_contents($dayFixture),$seed);
 $daySheet=json_decode($seed[1],true,512,JSON_THROW_ON_ERROR);
 GlobalRegistry::set('lang_code','pl');$_GET['url']='production-day';
 ob_start();$container->get(Controller::class)->view('production_day/index',['available'=>true,'has_shift'=>true,'view'=>'opening','sheet'=>$daySheet]);$dayHtml=ob_get_clean();
 $expect(str_contains($dayHtml,'app-tabbar-bar') && str_contains($dayHtml,'pd-footer'),'Production day fixture includes the actual bottom navigation');
 file_put_contents($fixtureDir.'/kitchen-runtime-day.html',$dayHtml);
}
// Simplified shell preserves real controls while removing retired panels.
GlobalRegistry::set('lang_code','pl');
$called=false;DeviceMode::refreshConfig(function()use(&$called){$called=true;return [];});
$expect(!$called,'Retired config endpoint is never requested');
foreach(['dashboard/dashboard'=>['items'=>[],'entry_employees'=>[]], 'me/about'=>['supplier'=>null], 'checklist/index'=>['progress'=>['checklist'=>['name'=>'Summary sentinel'],'tasks'=>[]]]] as $view=>$data){
 $_GET['url']=explode('/',$view)[0];ob_start();$container->get(Controller::class)->view($view,$data+['missing_api'=>['GET /devices/me/config']]);$html=ob_get_clean();
 $dom=new DOMDocument();@$dom->loadHTML($html);$xp=new DOMXPath($dom);
 $expect($xp->query('//*[contains(concat(" ",normalize-space(@class)," ")," api-todo ")]')->length===0,'No global missing API banner');
 if($view==='dashboard/dashboard'){
  $expect($xp->query('//div[@class="dash-grid"]/a')->length===4,'Exactly four dashboard launchers');
  $expect($xp->query('//div[contains(@class,"page-heading")] | //div[contains(@class,"dash-stats")] | //div[contains(@class,"dash-kb")]')->length===0,'Dashboard has no heading, stats or knowledge panel');
 }elseif($view==='me/about'){
  $expect($xp->query('//input[@name="mode" or @name="webshop_url" or @name="webshop_token"]')->length===0,'Retired device inputs removed');
  $expect($xp->query('//*[@id="soundOrderToggle"]')->length===1,'Audio settings remain available');
 }else{
  $expect($xp->query('//div[contains(@class,"page-heading")]')->length===0,'Checklist has no heading');
  $expect(!str_contains($html,'Summary sentinel'),'Selected checklist summary removed');
 }
}
$expect($container->get(\App\Kitchen\app\Http\Controllers\Production\ProductionPlanController::class) instanceof \App\Kitchen\app\Http\Controllers\Production\ProductionPlanController,'Plan controller resolves in the real container');
foreach(['pl','en','fr','it','nl'] as $language){
 GlobalRegistry::set('lang_code',$language);$_GET['url']='production-plan';
 foreach(['ready','empty','error'] as $state){
  $model=['date'=>'2026-11-04','cycle_distribution_available'=>true,'cycles'=>[
   ['key'=>'c1','name'=>'Morning','start'=>'06:00','end'=>'10:00'],['key'=>'c2','name'=>'Midday','start'=>'11:00','end'=>'15:00'],['key'=>'c3','name'=>'Evening','start'=>'16:00','end'=>'20:00']],
   'products'=>$state==='ready'?[['id_product'=>101,'name'=>'Bread <script>','quantity'=>12,'cycle_quantities'=>['c1'=>7,'c2'=>3,'c3'=>2],'shelf_life_category'=>'SSL','category_name'=>'Bread','sector_name'=>'Bakery']]:[]];
  ob_start();$container->get(Controller::class)->view('production_plan/index',['available'=>$state!=='error','offset'=>0,'model'=>$model]);$html=ob_get_clean();
  $dom=new DOMDocument();@$dom->loadHTML($html);$xp=new DOMXPath($dom);
  $expect($xp->query('//*[@data-plan-offset]')->length===3,'Exactly today, tomorrow and day after tomorrow');
  $expect(!str_contains($html,'Bread <script>'),'Plan product names are escaped');
  $expect($xp->query('//*[@data-plan-product]')->length===($state==='ready'?1:0),'Plan handles ready, empty and unavailable states');
  if($state!=='error')$expect($xp->query('//*[contains(@class,"pp-cycle-heading")]')->length===3,'Three configured cycle headings render');
  if($state==='ready')$expect($xp->query('//*[@data-plan-cycle]')->length===3,'Per-product quantities render for every cycle');
  if($language==='pl')file_put_contents($fixtureDir.'/kitchen-plan-'.$state.'.html',$html);
  if($state==='ready')foreach(['one-cycle','no-cycles','missing-category'] as $variant){
   $alternate=$model;
   if($variant==='one-cycle'){$alternate['cycles']=array_slice($model['cycles'],0,1);$alternate['products'][0]['cycle_quantities']=['c1'=>12];}
   if($variant==='no-cycles'){unset($alternate['cycles'],$alternate['cycle_distribution_available'],$alternate['products'][0]['cycle_quantities']);}
   if($variant==='missing-category')$alternate['products'][0]['cycle_quantities']=null;
   ob_start();$container->get(Controller::class)->view('production_plan/index',['available'=>true,'offset'=>0,'model'=>$alternate]);$alternateHtml=ob_get_clean();
   $alternateDom=new DOMDocument();@$alternateDom->loadHTML($alternateHtml);$alternateXp=new DOMXPath($alternateDom);
   $expect($alternateXp->query('//*[@data-plan-cycle]')->length===($variant==='no-cycles'?0:($variant==='one-cycle'?1:3)),'Cycle fallback and one-cycle day render '.$variant);
   $expect($alternateXp->query('//*[@data-plan-product]')->length===1,'Daily quantity remains visible '.$variant);
   if($language==='pl')file_put_contents($fixtureDir.'/kitchen-plan-'.$variant.'.html',$alternateHtml);
  }

 }
}
$cardProducts=[];
foreach([
 [101,'Bread SSL','SSL',1,'Bakery',10,'Bread',[[1,'Bakery group'],[2,'Breakfast']],true],
 [102,'Baguette SSL','SSL',1,'Bakery',11,'Baguettes',[[1,'Bakery group']],false],
 [103,'Cake MSL','MSL',2,'Kitchen',20,'Cakes',[[3,'Desserts']],false],
 [104,'Juice LSL','LSL',null,null,30,'Drinks',[],true],
 [105,'No category SSL','SSL',null,null,null,null,[],false],
 [106,'Other bread SSL','SSL',2,'Kitchen',40,'Bread',[[3,'Desserts']],false],
] as [$id,$name,$life,$sector,$sectorName,$category,$categoryName,$groups,$photo]){
 $row=['id'=>$id,'name'=>$name,'shelf_life_category'=>$life,'sector_id'=>$sector,'sector_name'=>$sectorName,'id_category'=>$category,'category_name'=>$categoryName,'category'=>['groups'=>array_map(fn($g)=>['id'=>$g[0],'name'=>$g[1]],$groups)],'shelf_life_minutes'=>300,'storage_name'=>'Cold'];
 if($photo)$row['main_photo_path']='r2://recipes/'.$id.'/main.jpg';
 $cardProducts[]=new \App\Kitchen\app\Models\Knowledge\Product\ProductModel($row);
}
foreach(['pl','en','fr','it','nl'] as $language){
 GlobalRegistry::set('lang_code',$language);$_GET['url']='knowledge/products';
 ob_start();$container->get(Controller::class)->view('knowledge/product/overview',['products'=>$cardProducts]);$html=ob_get_clean();
 $dom=new DOMDocument();@$dom->loadHTML($html);$xp=new DOMXPath($dom);
 $expect($xp->query('//*[@data-product-id]')->length===6,'Product models and filters render in every language');
 $cardCss=substr(hash_file('sha256',__DIR__.'/../public/assets/css/knowledge-products.css'),0,16);$cardJs=substr(hash_file('sha256',__DIR__.'/../public/assets/js/knowledge-products.js'),0,16);
 $expect(str_contains($html,'knowledge-products.css?v='.$cardCss)&&str_contains($html,'knowledge-products.js?v='.$cardJs),'Catalogue assets use content hashes to invalidate cache');
 $expect($xp->query('//*[@id="knowledgeProducts" and string-length(@data-all)>0]')->length===1&&$xp->query('//*[@data-card-options and string-length(@aria-label)>0]')->length===3,'Filter controls have translated labels');
 $expect($xp->query('//a[contains(@class,"kp-card")]//img')->length===2,'Product list uses real image URLs only where present');
 $expect($xp->query('//*[contains(@class,"kp-chip")]')->length===0,'Time and storage badges removed from list');
 $expect($xp->query('//*[@data-product-id and not(@hidden)]')->length===4,'Only SSL visible before JavaScript starts');
 if($language==='pl')file_put_contents($fixtureDir.'/kitchen-product-cards.html',$html);
}
GlobalRegistry::set('lang_code','pl');$_GET['url']='knowledge/products';
ob_start();$container->get(Controller::class)->view('knowledge/product/overview',['products'=>[],'catalog_unavailable'=>true]);$unavailable=ob_get_clean();
$expect(str_contains($unavailable,'role="alert"')&&!str_contains($unavailable,'id="productsGrid"'),'Catalogue API failure is shown explicitly, not as an empty product grid');
file_put_contents($fixtureDir.'/kitchen-product-cards-unavailable.html',$unavailable);
echo "$checks real controller navigation checks passed\n";
