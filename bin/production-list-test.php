<?php
require __DIR__ . '/../vendor/autoload.php';
use App\Kitchen\app\Repositories\ProductionList\ProductionListRepository;
use App\Kitchen\app\Services\ProductionList\ProductionListService;
use App\Kitchen\core\Http\ApiClient;
use App\Kitchen\core\Support\GlobalRegistry;
use App\Kitchen\core\Support\ShiftSession;

$checks = 0;
$check = function ($condition, $label) use (&$checks) { ++$checks; if (!$condition) throw new RuntimeException($label); };
define('JWT_SECRET_KEY', 'production-list-test-only');
$api = new class extends ApiClient {
    public array $calls = [];
    public function __construct() {}
    public function where($endpoint, $params, $decode_json = true) {
        $this->calls[] = [$endpoint, $params];
        return ['success' => true, 'data' => ['revision' => 3, 'lines' => [['id_product' => 1, 'name' => 'Test bread', 'planned_quantity' => 12, 'produced_quantity' => 5, 'remaining_quantity' => 7]]]];
    }
    public function post($endpoint, $data) { $this->calls[] = [$endpoint, $data]; return ['success' => true]; }
    public function put($endpoint, $data) { $this->calls[] = [$endpoint, $data]; return ['success' => true]; }
};
GlobalRegistry::set('user', ['shop_id' => 2, 'device_id' => 7]);
$service = new ProductionListService(new ProductionListRepository($api));
$check($service->readDate('2026-02-30') === date('Y-m-d'), 'Reject impossible date');
$check($service->readMargin(-5) === 0.0 || $service->readMargin(-5) === 0, 'No negative margin');
$check($service->build(date('Y-m-d'), 2)['revision'] === 3, 'Pass optimistic revision');
$check(count($api->calls) === 1, 'Single API read for plan');
$invalid = $service->save(['date' => '2026-02-30', 'margin' => 0, 'revision' => 0, 'lines' => []]);
$check(!$invalid['success'] && count($api->calls) === 1, 'Invalid save never reaches API');
$service->save(['date' => date('Y-m-d'), 'margin' => 2, 'revision' => 3, 'shop_id' => 999, 'lines' => [['id_product' => 1, 'planned_quantity' => 12]]]);
$check($api->calls[1][0] === '/shops/2/production-plans', 'Ignore user supplied shop');
$payload = ['date' => date('Y-m-d'), 'id_product' => 1, 'quantity' => 4, 'idempotency_key' => 'receipt-retry-test-001', 'id_employee' => 999, 'id_device' => 999];
$check(($service->receive($payload)['reason'] ?? '') === 'shift_required', 'Require signed active shift');
$_COOKIE[ShiftSession::COOKIE] = ShiftSession::rules()->sign(8, 'Test employee', time(), time(), JWT_SECRET_KEY, 2, date('Y-m-d'));
$service->receive($payload);
$call = $api->calls[2][1];
$check($call['id_employee'] === 8 && $call['id_device'] === 7, 'Never trust browser actor fields');
$check($call['idempotency_key'] === $payload['idempotency_key'], 'Preserve retry key');
$payload['date'] = '2020-01-01';
$check(!$service->receive($payload)['success'] && count($api->calls) === 3, 'Reject other day receipt');
$payload['date'] = date('Y-m-d'); $payload['quantity'] = -1;
$check(!$service->receive($payload)['success'], 'Reject negative receipt');

$loader = new Twig\Loader\FilesystemLoader(__DIR__ . '/../src/app/Views');
$loader = new Twig\Loader\ChainLoader([new Twig\Loader\ArrayLoader(['layouts/base.twig' => '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;padding:20px;background:#f6f2ed;font-family:Arial,sans-serif}*{box-sizing:border-box}</style>{% block head %}{% endblock %}</head><body>{% block content %}{% endblock %}</body></html>']), $loader]);
$twig = new Twig\Environment($loader, ['autoescape' => 'html']);
$data = ['ROOT' => '/kitchen', 'plan_date' => date('Y-m-d'), 'margin' => 2, 'revision' => 1, 'available' => true,
    'history_from' => '2026-08-12', 'history_to' => '2026-09-08', 'is_today' => true, 'has_shift' => true,
    'lines' => [['id_product' => 1, 'name' => 'Chleb pszenny', 'sector_id'=>11, 'sector_name'=>'Piekarnia', 'id_category'=>1, 'category_name'=>'Pieczywo', 'average_quantity' => 10.25, 'ordered_quantity' => 3.75, 'suggested_quantity' => 16, 'planned_quantity' => 18, 'produced_quantity' => 10, 'remaining_quantity' => 8, 'inventory_tracking_enabled' => true, 'inventory_initialized_at'=>'2026-09-09 00:00:00'], ['id_product' => 2, 'name' => 'Croissant migdałowy', 'sector_id'=>12, 'sector_name'=>'Cukiernia', 'id_category'=>2, 'category_name'=>'Ciastka', 'average_quantity' => 24, 'ordered_quantity' => 4, 'suggested_quantity' => 30, 'planned_quantity' => 30, 'produced_quantity' => 0, 'remaining_quantity' => 30, 'inventory_tracking_enabled' => true, 'inventory_initialized_at'=>null]], 'excluded_products' => []];
foreach (['pl','en','fr','nl','it'] as $lang) {
    $data['translations'] = json_decode(file_get_contents(__DIR__ . '/../src/core/I18n/translations/page/' . $lang . '/production_list.json'), true, 512, JSON_THROW_ON_ERROR);
    $html = $twig->render('production_list/index.twig', $data);
    $check(str_contains($html, $data['translations']['receive']), 'Translated receipt action: ' . $lang);
    if ($lang === 'pl') file_put_contents('/tmp/tfb-production-iteration.html', $html);
    $historyData=$data;
    $historyData['lines']=[];
    $historyData['production_history']=[['name'=>'Archived production item','planned_quantity'=>12,'produced_quantity'=>5]];
    $historyHtml=$twig->render('production_list/index.twig',$historyData);
    $check(str_contains($historyHtml,'Archived production item') && str_contains($historyHtml,$data['translations']['production_history']), 'Disabled history remains visible: '.$lang);
    $check(!str_contains($historyHtml,'class="pl-receive"') && !str_contains($historyHtml,'class="pl-plan-input"'), 'History cannot submit new production: '.$lang);
    $savedData=$data+['saved_plan'=>['exists'=>true,'revision'=>2,'totals'=>['planned'=>18,'produced'=>10,'remaining'=>8],
        'categories'=>[['name'=>'Pieczywo','lines'=>[$data['lines'][0]]]],'sectors'=>[['name'=>'Piekarnia','categories'=>[['name'=>'Pieczywo','lines'=>[$data['lines'][0]]]]]],'production_history'=>[]]];
    $savedHtml=$twig->render('production_list/saved.twig',$savedData);
    $check(str_contains($savedHtml,'Pieczywo') && str_contains($savedHtml,'<td>18</td>'),'Saved view uses saved quantity, not proposal '.$lang);
    $check(str_contains($savedHtml,'/production/pdf?date='),'PDF link only on saved view '.$lang);
    $check(str_contains($savedHtml,'Piekarnia') && str_contains($savedHtml,'<h3>Pieczywo</h3>'),'Saved sector and category hierarchy '.$lang);
    $check(str_contains($html,'id="plGrouping"') && str_contains($html,'data-sector="Piekarnia"') && str_contains($html,'data-category="Pieczywo"'),'Grouping data and selector '.$lang);
    $check(!str_contains($html,'window.print()'),'No printing of editable sheet '.$lang);
    $emptyData=$data;
    $emptyData['saved_plan']=['exists'=>false,'latest_saved_date'=>'2026-09-01'];
    $emptyHtml=$twig->render('production_list/saved.twig',$emptyData);
    $check(str_contains($emptyHtml,'/production?date=2026-09-01') && str_contains($emptyHtml,$data['translations']['open_latest_plan']), 'Discover saved plan on another day '.$lang);
    $check(str_contains($emptyHtml,'value="'.$data['plan_date'].'"') && !str_contains($emptyHtml,'/production/pdf?date='),'Empty day stays selected without PDF '.$lang);
    $emptyData['saved_plan']['latest_saved_date']=null;
    $check(!str_contains($twig->render('production_list/saved.twig',$emptyData),$data['translations']['open_latest_plan']),'No link when shop has no saved plans '.$lang);
    if($lang==='pl')file_put_contents('/tmp/tfb-production-saved.html',$savedHtml);
}
echo "Production list: {$checks} checks passed. Fixture: /tmp/tfb-production-iteration.html\n";
