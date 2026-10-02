<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Kitchen\app\Repositories\Inventory\CarryoverSettlementRepository;
use App\Kitchen\app\Services\Inventory\CarryoverSettlementService;
use App\Kitchen\core\Http\ApiClient;
use App\Kitchen\core\Support\GlobalRegistry;
use App\Kitchen\core\Support\ShiftSession;

$checks = 0;
$check = static function (bool $condition, string $label) use (&$checks): void { ++$checks; if (!$condition) throw new RuntimeException($label); };
define('JWT_SECRET_KEY', 'carryover-settlement-test-only');
$today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Brussels')))->format('Y-m-d');
$tomorrow = (new DateTimeImmutable($today, new DateTimeZone('Europe/Brussels')))->modify('+1 day')->format('Y-m-d');
$api = new class($today, $tomorrow) extends ApiClient {
    public array $calls = [];
    public array $postResponse = ['success' => true, 'data' => ['settlement_id' => 3, 'items' => []]];
    public function __construct(private string $today, private string $tomorrow) {}
    public function get($endpoint, $decodeJson = true) {
        $this->calls[] = ['GET', $endpoint];
        return ['success' => true, 'data' => [
            'source_date' => $this->today, 'target_date' => $this->tomorrow, 'timezone' => 'Europe/Brussels',
            'waste_reasons' => [['key' => 'expiration', 'label' => 'Expired', 'comment_required' => false], ['key' => 'other_waste', 'label' => 'Other', 'comment_required' => true]],
            'items' => [
                ['id_product' => 1, 'product_name' => 'Bread', 'in_stock' => '9.0000', 'last_movement_id' => '41', 'supported' => true, 'status' => 'needs_decision', 'settlement' => null],
                ['id_product' => 2, 'product_name' => 'Flour', 'in_stock' => '3.0000', 'last_movement_id' => '42', 'supported' => false, 'status' => 'unsupported', 'settlement' => null],
                ['id_product' => 3, 'product_name' => 'Bun', 'in_stock' => '6.0000', 'last_movement_id' => '45', 'supported' => true, 'status' => 'settled', 'settlement' => ['carryover_quantity' => '6.0000', 'waste_quantity' => '3.0000', 'waste_reason_label' => 'Expired', 'waste_comment' => null, 'state_changed_after_settlement' => true]],
            ],
            'from_previous_day' => [['id_product' => 4, 'product_name' => 'Croissant', 'source_date' => '2026-10-01', 'target_date' => $this->today, 'carryover_quantity' => '2.0000']],
        ]];
    }
    public function post($endpoint, $payload) { $this->calls[] = ['POST', $endpoint, $payload]; return $this->postResponse; }
};
GlobalRegistry::set('user', ['shop_id' => 2, 'device_id' => 7]);
$service = new CarryoverSettlementService(new CarryoverSettlementRepository($api));
$screen = $service->screen();
$check($screen['available'] && $screen['source_date'] === $today && !$screen['has_shift'], 'Anonymous user can read API-defined source date');
$check($screen['target_date'] === $tomorrow && count($screen['from_previous_day']) === 1, 'Target date and previous-day carryover are presented separately');
$input = ['idempotency_key' => 'carryover-kitchen-key-001', 'lines' => [[
    'id_product' => 1, 'carryover_quantity' => '6', 'waste_quantity' => '3', 'waste_reason' => 'expiration', 'waste_comment' => null,
    'expected_in_stock' => '9.0000', 'expected_movement_id' => '41',
]]];
$check($service->create($input)['reason'] === 'shift_required', 'Anonymous user cannot approve settlement');
$_COOKIE[ShiftSession::COOKIE] = ShiftSession::rules()->sign(8, 'Ana', time(), time(), JWT_SECRET_KEY, 2, $today);
$check($service->create($input)['success'], 'Signed shift submits one atomic settlement');
$post = end($api->calls);
$check($post[1] === '/shops/2/carryover-settlement' && $post[2] === $input && !isset($post[2]['id_device'], $post[2]['id_employee']), 'Browser cannot supply trusted actor or dates');
$invalid = $input; $invalid['lines'][0]['waste_quantity'] = '2';
$check($service->create($invalid)['reason'] === 'invalid_input', 'Invalid carryover plus waste sum rejected before write');
$invalid = $input; $invalid['lines'][0]['waste_reason'] = 'other_waste';
$check($service->create($invalid)['reason'] === 'invalid_input', 'other_waste note required before write');
$api->postResponse = ['success' => false, 'code' => 409, 'description' => 'CARRYOVER_SHEET_CHANGED', 'errors' => ['required_product_ids' => [1, 4]]];
$conflict = $service->create($input);
$check($conflict['reason'] === 'conflict' && $conflict['errors']['required_product_ids'] === [1, 4], 'Conflict data reaches the mobile view');

$loader = new Twig\Loader\ChainLoader([
    new Twig\Loader\ArrayLoader(['layouts/base.twig' => '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;padding:16px;background:#f6f2ed;font-family:Arial}*{box-sizing:border-box}</style>{% block head %}{% endblock %}</head><body>{% block content %}{% endblock %}</body></html>']),
    new Twig\Loader\FilesystemLoader(__DIR__ . '/../src/app/Views'),
]);
$twig = new Twig\Environment($loader, ['autoescape' => 'html']);
foreach (['pl', 'en', 'fr', 'nl', 'it'] as $language) {
    $translations = json_decode(file_get_contents(__DIR__ . '/../src/core/I18n/translations/page/' . $language . '/inventory.json'), true, 512, JSON_THROW_ON_ERROR);
    $html = $twig->render('inventory/carryover.twig', array_merge($screen, ['ROOT' => '/kitchen', 'translations' => $translations, 'has_shift' => true]));
    $check(str_contains($html, $translations['tab_tomorrow']) && str_contains($html, 'cpProducts'), "Carryover view renders $language");
    $check(str_contains($html, 'carryover_quantity') && str_contains($html, 'waste_quantity'), "Explicit decisions rendered $language");
    if ($language === 'pl') file_put_contents('/tmp/kitchen-carryover-fixture.html', $html);
}
echo "Kitchen carryover settlement: {$checks} checks passed\n";
