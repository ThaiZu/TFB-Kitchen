<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Kitchen\app\Repositories\Inventory\InventoryRepository;
use App\Kitchen\app\Repositories\Inventory\WasteReportRepository;
use App\Kitchen\app\Services\Inventory\WasteReportService;
use App\Kitchen\core\Http\ApiClient;
use App\Kitchen\core\Support\GlobalRegistry;
use App\Kitchen\core\Support\ShiftSession;

$checks = 0;
$check = static function (bool $condition, string $label) use (&$checks): void {
    ++$checks;
    if (!$condition) throw new RuntimeException($label);
};

define('JWT_SECRET_KEY', 'waste-report-test-only');
$today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Brussels')))->format('Y-m-d');
$api = new class($today) extends ApiClient {
    public array $calls = [];
    public array $postResponse = ['success' => true, 'data' => ['report_id' => 20, 'idempotent' => false, 'items' => []]];
    public function __construct(private string $today) {}
    public function get($endpoint, $decodeJson = true) {
        $this->calls[] = ['GET', $endpoint];
        if (str_ends_with($endpoint, '/inventory-count')) return ['success' => true, 'data' => ['today' => $this->today, 'lines' => [
            ['id_product' => 1, 'name' => 'Bread', 'in_stock' => '12.0000', 'inventory_initialized_at' => '2026-10-01', 'last_movement_id' => '41', 'is_piece_based' => 1, 'is_divisible' => 0],
            ['id_product' => 2, 'name' => 'Weight', 'in_stock' => '3.0000', 'inventory_initialized_at' => '2026-10-01', 'last_movement_id' => '42', 'is_piece_based' => 0, 'is_divisible' => 0],
            ['id_product' => 3, 'name' => 'Unknown', 'in_stock' => '0.0000', 'inventory_initialized_at' => null, 'last_movement_id' => '0', 'is_piece_based' => 1, 'is_divisible' => 0],
        ]]];
        if (str_ends_with($endpoint, '/waste-reasons')) return ['success' => true, 'data' => ['items' => [
            ['key' => 'expiration', 'label' => 'Expired', 'comment_required' => false],
            ['key' => 'carryover', 'label' => 'Carryover', 'comment_required' => false],
            ['key' => 'other_waste', 'label' => 'Other', 'comment_required' => true],
        ]]];
        if (str_ends_with($endpoint, '/waste-reports/today')) return ['success' => true, 'data' => ['date' => $this->today, 'timezone' => 'Europe/Brussels', 'items' => [
            ['product_name' => 'Bread', 'quantity' => 1, 'reason_label' => 'Expired', 'employee_name' => 'Ana', 'created_at' => $this->today . 'T08:35:00+02:00'],
        ]]];
        return ['success' => false];
    }
    public function post($endpoint, $payload) { $this->calls[] = ['POST', $endpoint, $payload]; return $this->postResponse; }
};

GlobalRegistry::set('user', ['shop_id' => 2, 'device_id' => 7]);
$service = new WasteReportService(new InventoryRepository($api), new WasteReportRepository($api));
$screen = $service->screen();
$check($screen['available'] && $screen['reasons_available'] && $screen['history_available'], 'Load all actual API read models');
$check($screen['today'] === $today && !$screen['has_shift'], 'Store date used and anonymous browsing allowed');
$check(!in_array('carryover', array_column($screen['reasons'], 'key'), true), 'Carryover is never exposed as a waste reason');

$input = ['idempotency_key' => 'waste-test-key-0001', 'lines' => [
    ['id_product' => 1, 'quantity' => '3', 'reason' => 'expiration', 'expected_in_stock' => '12.0000', 'expected_movement_id' => '41'],
]];
$check($service->create($input)['reason'] === 'shift_required', 'Anonymous mode cannot write');
$_COOKIE[ShiftSession::COOKIE] = ShiftSession::rules()->sign(8, 'Ana', time(), time(), JWT_SECRET_KEY, 2, $today);
$result = $service->create($input);
$check($result['success'], 'Atomic API report is delegated after signed shift check');
$post = end($api->calls);
$check($post[1] === '/shops/2/waste-reports' && !isset($post[2]['id_device'], $post[2]['id_employee'], $post[2]['shop_id']), 'Trusted actor is never submitted by browser');
$check($post[2] === $input, 'Payload retains idempotency and concurrency fields');
$multiple = $input;
$multiple['idempotency_key'] = 'waste-test-key-0002';
$multiple['lines'][] = ['id_product' => 4, 'quantity' => '1', 'reason' => 'expiration', 'expected_in_stock' => '4.0000', 'expected_movement_id' => '44'];
$check($service->create($multiple)['success'] && count(end($api->calls)[2]['lines']) === 2, 'Several products stay in one atomic API request');

$invalid = $input;
$invalid['lines'][0]['reason'] = 'other_waste';
$before = count(array_filter($api->calls, static fn (array $call): bool => $call[0] === 'POST'));
$check($service->create($invalid)['reason'] === 'invalid_input'
    && count(array_filter($api->calls, static fn (array $call): bool => $call[0] === 'POST')) === $before,
    'Other waste without a note is rejected before write');
$invalid['lines'][0]['comment'] = str_repeat('x', 501);
$check($service->create($invalid)['reason'] === 'invalid_input', 'Overlong note rejected');
$invalid['lines'][0]['comment'] = 'Dropped';
$invalid['lines'][0]['quantity'] = '1.5';
$check($service->create($invalid)['reason'] === 'invalid_input', 'Fractional pieces rejected');
$invalid['lines'][0]['quantity'] = '1';
$invalid['lines'][] = ['id_product' => 1, 'quantity' => '1', 'reason' => 'expiration', 'expected_in_stock' => '12', 'expected_movement_id' => '41'];
$check($service->create($invalid)['reason'] === 'invalid_input', 'Duplicate product cannot create ambiguous API report');

$api->postResponse = ['success' => false, 'code' => 409, 'description' => 'INVENTORY_STATE_CHANGED', 'errors' => ['current_state' => ['in_stock' => '9.0000']]];
$conflict = $service->create($input);
$check($conflict['reason'] === 'conflict' && $conflict['errors']['current_state']['in_stock'] === '9.0000', 'Stock conflict reaches browser boundary with refresh data');

$loader = new Twig\Loader\ChainLoader([
    new Twig\Loader\ArrayLoader(['layouts/base.twig' => '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;padding:16px;background:#f6f2ed;font-family:Arial}*{box-sizing:border-box}</style><script>window.bootstrap={Modal:function(){this.show=function(){};this.hide=function(){}}}</script>{% block head %}{% endblock %}</head><body>{% block content %}{% endblock %}</body></html>']),
    new Twig\Loader\FilesystemLoader(__DIR__ . '/../src/app/Views'),
]);
$twig = new Twig\Environment($loader, ['autoescape' => 'html']);
foreach (['pl', 'en', 'fr', 'nl', 'it'] as $language) {
    $translations = json_decode(file_get_contents(__DIR__ . '/../src/core/I18n/translations/page/' . $language . '/inventory.json'), true, 512, JSON_THROW_ON_ERROR);
    $html = $twig->render('inventory/waste.twig', array_merge($screen, ['ROOT' => '/kitchen', 'translations' => $translations, 'has_shift' => true]));
    $check(str_contains($html, $translations['tab_waste']) && str_contains($html, 'wpProductModal'), "Waste view renders $language");
    $check(!str_contains($html, '"key":"carryover"'), "No carryover reason in $language");
    if ($language === 'pl') file_put_contents('/tmp/kitchen-waste-fixture.html', $html);
}

echo "Kitchen waste report: {$checks} checks passed\n";
