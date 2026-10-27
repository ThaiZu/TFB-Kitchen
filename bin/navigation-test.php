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
$expect(DeviceMode::rules()->navKeys('production') === [], 'Reproduce missing runtime device configuration');
$expect(DeviceMode::rules()->tabKeys('production') === [], 'Reproduce empty runtime tabs');

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
                $destinations = ['dashboard', 'production-day', 'knowledge/products', 'checklists', 'orders', 'knowledge'];
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
echo "$checks real controller navigation checks passed\n";
