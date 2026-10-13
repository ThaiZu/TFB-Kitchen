<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Kitchen\app\Repositories\Production\TodayProductionRepository;
use App\Kitchen\app\Services\Production\TodayProductionService;
use App\Kitchen\core\Http\ApiClient;
use App\Kitchen\core\Support\GlobalRegistry;

$checks = 0;
$check = static function (bool $condition, string $label) use (&$checks): void {
    ++$checks;
    if (!$condition) {
        throw new RuntimeException($label);
    }
};

$api = new class extends ApiClient {
    public array $calls = [];
    public string $now = '05:00';
    public function __construct() {}

    public function get($endpoint, $decodeJson = true): array
    {
        $this->calls[] = $endpoint;
        return ['success' => true, 'data' => [
            'date' => '2026-10-13',
            'id_plan' => '7001',
            'now' => $this->now,
            'exists' => true,
            'cycles' => [[
                'id_cycle' => '8001',
                'key' => 'c1',
                'name' => 'Rano',
                'time' => '06:00',
                'end_time' => '10:00',
                'lines' => [[
                    'id_cycle_line' => '9001',
                    'id_product' => 10,
                    'name' => 'Croissant',
                    'quantity' => '12.0000',
                    'target_quantity' => '14.0000',
                    'id_category' => 4,
                    'category_name' => 'Viennoiseries',
                ]],
            ], [
                'id_cycle' => '8002',
                'key' => 'c2',
                'name' => 'Midi',
                'time' => '10:00',
                'end_time' => '13:00',
                'lines' => [[
                    'id_cycle_line' => '9002',
                    'id_product' => 10,
                    'name' => 'Croissant',
                    'quantity' => '4.0000',
                    'target_quantity' => '4.0000',
                    'id_category' => 4,
                    'category_name' => 'Viennoiseries',
                ], [
                    'id_cycle_line' => '9003',
                    'id_product' => 11,
                    'name' => 'Baguette',
                    'quantity' => '8.0000',
                    'target_quantity' => '8.0000',
                    'id_category' => 2,
                    'category_name' => 'Pains',
                ]],
            ]],
        ]];
    }
};

GlobalRegistry::set('user', ['shop_id' => 7]);
$view = (new TodayProductionService(new TodayProductionRepository($api)))->view();
$check($api->calls === ['/shops/7/production/today'], 'Uses the Kitchen-only today endpoint for the authenticated shop');
$check($view['available'] && $view['exists'], 'Exposes an available canonical plan');
$check($view['id_plan'] === '7001' && $view['cycles'][0]['id_cycle'] === '8001', 'Keeps only server-owned confirmation identifiers');
$check(count($view['cycles']) === 2 && $view['cycles'][0]['is_current'], 'Marks the nearest upcoming cycle before it starts');
$api->now = '09:30';
$during = (new TodayProductionService(new TodayProductionRepository($api)))->view();
$check($during['cycles'][0]['is_current'] && !$during['cycles'][1]['is_current'], 'Keeps the running cycle highlighted until its end');
$check($view['categories'][0]['name'] === 'Pains' && $view['categories'][1]['name'] === 'Viennoiseries', 'Groups products by category in stable order');
$check($view['categories'][1]['lines'][0]['quantities'] === ['c1' => '12.0000', 'c2' => '4.0000'], 'Combines each product across cycle columns');
$check($view['categories'][1]['lines'][0]['total_quantity'] === '16.0000', 'Calculates the remaining total across all cycles');
$check($view['categories'][1]['lines'][0]['target_quantities'] === ['c1' => '14.0000', 'c2' => '4.0000'], 'Keeps an adjusted cycle target separate from the remaining quantity');
$check($view['categories'][1]['lines'][0]['cycle_lines'] === ['c1' => '9001', 'c2' => '9002'], 'Keeps opaque cycle-line IDs for the target-save contract');
$invalidTarget=(new TodayProductionService(new TodayProductionRepository($api)))->adjustCycleTarget([]);
$check($invalidTarget === ['success'=>false,'reason'=>'invalid_input','code'=>422], 'Rejects malformed target saves without turning a recoverable validation error into a server error');

foreach (['pl', 'en', 'fr', 'nl', 'it'] as $language) {
    $translations = json_decode(file_get_contents(__DIR__ . '/../src/core/I18n/translations/page/' . $language . '/production_today.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach (['title', 'reload', 'report_waste', 'unavailable', 'no_plan', 'empty', 'starts_at', 'product', 'to_make', 'uncategorized', 'total', 'confirm_cycle', 'confirm_question', 'confirming', 'confirmed', 'confirm_conflict', 'confirm_failed', 'shift_required', 'adjust_help', 'edit_cycle', 'save_cycle', 'cancel_edit', 'increase', 'decrease', 'saving_target', 'target_saved', 'target_failed', 'target_conflict'] as $key) {
        $check(is_string($translations[$key] ?? null) && $translations[$key] !== '', 'Translation ' . $language . ': ' . $key);
    }
}

echo "Kitchen production today: {$checks} checks passed\n";
