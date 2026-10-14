<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Kitchen\app\Repositories\Material\MaterialStocktakingRepository;
use App\Kitchen\app\Services\Material\MaterialStocktakingService;
use App\Kitchen\core\Http\ApiClient;
use App\Kitchen\core\Support\GlobalRegistry;
use App\Kitchen\core\Support\ShiftSession;

define('JWT_SECRET_KEY', 'material-stocktaking-test-only');
$checks = 0;
$check = static function (bool $condition, string $label) use (&$checks): void { ++$checks; if (!$condition) throw new RuntimeException($label); };

$api = new class extends ApiClient {
    public array $calls = [];
    public function __construct() {}
    public function get($endpoint, $decodeJson = true): array {
        $this->calls[] = ['GET', $endpoint];
        return ['success' => true, 'data' => [
            ['id_material' => 10, 'material_name' => 'Flour', 'category_name' => 'Raw', 'unit_name' => 'kg', 'current_quantity' => '5.5000'],
            ['id_material' => 11, 'material_name' => 'Butter', 'category_name' => 'Raw', 'unit_name' => 'kg', 'current_quantity' => '0.0000'],
        ]];
    }
    public function post($endpoint, $payload): array {
        $this->calls[] = ['POST', $endpoint, $payload];
        if (str_ends_with($endpoint, '/verify-codes')) return ['success' => true, 'data' => ['data' => [[
            'code' => '12345678901234567890', 'id_package' => 50, 'id_material' => 10, 'name' => 'Flour', 'unit_name' => 'kg',
            'package_size_in_base_unit' => '2.5', 'id_unit' => 1,
        ]]]];
        return ['success' => true, 'inserted_id' => 99, 'data' => ['inserted_id' => 99]];
    }
};

GlobalRegistry::set('user', ['shop_id' => 7, 'device_id' => 3]);
ShiftSession::open(17, 'Anna', 7, '2026-10-13');
$service = new MaterialStocktakingService(new MaterialStocktakingRepository($api));
$screen = $service->screen();
$check($screen['available'] && $screen['has_shift'] && count($screen['lines']) === 2, 'Loads material inventory and signed worker context');
$check($screen['lines'][0]['name'] === 'Butter' && $screen['lines'][1]['unit'] === 'kg', 'Normalises material labels and units');
$preview = $service->previewScanner(['codes' => ['12345678901234567890']]);
$check($preview['success'] && $preview['items'][0]['id_material'] === 10 && $preview['items'][0]['quantity'] === 1, 'Uses the established scanner decoder');
$manual = $service->save(['type' => 'manual', 'quantities' => ['10' => '6.2500', '11' => '0']]);
$check($manual === ['success' => true, 'inserted_id' => 99], 'Saves a complete manual material count');
$last = end($api->calls);
$check($last[1] === '/shops/7/materials/stocktakings' && $last[2]['type'] === 'MANUAL' && $last[2]['id_employee'] === 17 && count($last[2]['elements']) === 2, 'Manual count sends all materials with server-owned employee');
$incomplete = $service->save(['type' => 'manual', 'quantities' => ['10' => '6.2500']]);
$check($incomplete === ['success' => false, 'reason' => 'manual_incomplete', 'code' => 422], 'Rejects partial manual counts before they could clear hidden materials');
$scanner = $service->save(['type' => 'scanner', 'codes' => ['12345678901234567890']]);
$check($scanner['success'], 'Verifies scanner codes again immediately before saving');

$loader = new Twig\Loader\ChainLoader([new Twig\Loader\ArrayLoader(['layouts/base.twig' => '<html>{% block head %}{% endblock %}{% block content %}{% endblock %}{% block scripts %}{% endblock %}</html>']), new Twig\Loader\FilesystemLoader(__DIR__ . '/../src/app/Views')]);
$twig = new Twig\Environment($loader, ['autoescape' => 'html']);
foreach (['pl', 'en', 'fr', 'nl', 'it'] as $language) {
    $translations = json_decode(file_get_contents(__DIR__ . '/../src/core/I18n/translations/page/' . $language . '/material_stocktaking.json'), true, 512, JSON_THROW_ON_ERROR);
    $html = $twig->render('material_stocktaking/index.twig', array_merge($screen, ['ROOT' => '/kitchen', 'translations' => $translations]));
    $check(str_contains($html, 'id="msCode"') && str_contains($html, 'type="search"') && str_contains($html, 'id="msManualLines"') && str_contains($html, 'grid-template-columns:minmax(0,1fr) 176px') && str_contains($html, 'quantityForInput') && str_contains($html, "kitchen:worker-session-expired"), "Tablet scanner, readable quantity controls, expiry recovery and complete manual count render in $language");
}
$dispatcher = FastRoute\simpleDispatcher(require __DIR__ . '/../src/core/Bootstrap/routes.php');
foreach ([['GET', '/material-stocktaking'], ['POST', '/ajax/material-stocktaking/preview'], ['POST', '/ajax/material-stocktaking/save']] as [$method, $path]) $check($dispatcher->dispatch($method, $path)[0] === FastRoute\Dispatcher::FOUND, "Route resolves: $method $path");

echo "Kitchen material stocktaking: {$checks} checks passed\n";
