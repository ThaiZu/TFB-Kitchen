<?php
require __DIR__.'/../vendor/autoload.php';
use App\Kitchen\app\Repositories\Inventory\InventoryRepository;
use App\Kitchen\app\Services\Inventory\InventoryService;
use App\Kitchen\app\Repositories\ProductionList\ProductionListRepository;
use App\Kitchen\app\Services\ProductionList\ProductionListService;
use App\Kitchen\core\Http\ApiClient;
use App\Kitchen\core\Support\GlobalRegistry;
use App\Kitchen\core\Support\ShiftSession;
$checks=0;
$check=static function(bool $condition,string $label) use(&$checks):void {++$checks;if(!$condition)throw new RuntimeException($label);};
define('JWT_SECRET_KEY','inventory-test-only');
$today=(new DateTimeImmutable('now',new DateTimeZone('Europe/Brussels')))->format('Y-m-d');
$api=new class($today) extends ApiClient {
    public array $calls=[];
    public array $response=['success'=>true];
    public function __construct(private string $today){}
    public function get($endpoint,$decode_json=true){$this->calls[]=[$endpoint];return ['success'=>true,'data'=>['today'=>$this->today,'lines'=>[]]];}
    public function post($endpoint,$data){$this->calls[]=[$endpoint,$data];return $this->response;}
};
GlobalRegistry::set('user',['shop_id'=>2,'device_id'=>7]);
$service=new InventoryService(new InventoryRepository($api));
$data=$service->snapshot();$check($data['snapshot_date']===$today&&!$data['has_shift'],'Shop date and missing shift');
$payload=['date'=>$today,'id_product'=>5,'in_stock'=>'0','expected_in_stock'=>'25.1250','expected_initialized_at'=>'2026-09-01 00:00:00.000000','expected_movement_id'=>'184467440737095516','idempotency_key'=>'inventory-test-key-001','id_employee'=>999,'id_device'=>999,'shop_id'=>999];
$check($service->count($payload)['reason']==='shift_required','No write without signed shift');
$_COOKIE[ShiftSession::COOKIE]=ShiftSession::rules()->sign(8,'Employee',time(),time(),JWT_SECRET_KEY,2,$today);
$check($service->count($payload)['success'],'Explicit zero accepted');
$call=$api->calls[1];$check($call[0]==='/shops/2/inventory-count/5','Trusted shop scope');
$check($call[1]['id_employee']===8&&$call[1]['id_device']===7,'Trusted actor context');
$check($call[1]['expected_movement_id']==='184467440737095516','Movement id retained as string');
$check($call[1]['in_stock']==='0'&&$call[1]['expected_in_stock']==='25.1250','Preserve count and snapshot precision');
foreach (['','-1','1.00001',true,'1e2'] as $value){$invalid=$payload;$invalid['in_stock']=$value;$check(!$service->count($invalid)['success'],'Reject invalid count '.var_export($value,true));}
$unknown=$payload;$unknown['expected_initialized_at']=null;$check($service->count($unknown)['success'],'Explicit count initializes unknown state');
$api->response=['success'=>false,'code'=>409];$check($service->count($payload)['reason']==='conflict','Readable snapshot conflict');
$api->response=['success'=>true];
$production=new ProductionListService(new ProductionListRepository($api));
$receipt=['date'=>$today,'id_product'=>5,'quantity'=>8,'idempotency_key'=>'receipt-test-key-001','expected_revision'=>3,'expected_produced_quantity'=>'12.1250'];
$check($production->receive($receipt)['success'],'Production receipt accepted');
$last=end($api->calls);$check($last[1]['expected_revision']===3&&$last[1]['expected_produced_quantity']===12.125,'Forward production snapshot');
unset($receipt['expected_revision']);$check(!$production->receive($receipt)['success'],'Reject incomplete production snapshot');
$loader=new Twig\Loader\ChainLoader([new Twig\Loader\ArrayLoader(['layouts/base.twig'=>'<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;padding:16px;background:#f6f2ed;font-family:Arial}*{box-sizing:border-box}</style>{% block head %}{% endblock %}</head><body>{% block content %}{% endblock %}</body></html>']),new Twig\Loader\FilesystemLoader(__DIR__.'/../src/app/Views')]);
$twig=new Twig\Environment($loader,['autoescape'=>'html']);
$lines=[];
foreach ([[1,'Bread',25,'2026-09-01'],[2,'Zero',0,'2026-09-01'],[3,'Unknown',0,null],[4,'Weighted',2,'2026-09-01'],[5,'Legacy placeholder',999,null]] as [$id,$name,$stock,$initialized])$lines[]=['id_product'=>$id,'name'=>$name,'in_stock'=>(string)$stock,'inventory_initialized_at'=>$initialized,'last_movement_id'=>'45','is_piece_based'=>$id!==4,'is_divisible'=>false,'reserved_quantity'=>0];
$view=['ROOT'=>'/kitchen','shop_id'=>2,'available'=>true,'snapshot_date'=>$today,'plan_date'=>$today,'has_shift'=>true,'is_today'=>true,'lines'=>$lines];
$planLines=[['id_product'=>1,'name'=>'Bread','planned_quantity'=>25,'produced_quantity'=>10,'remaining_quantity'=>15],['id_product'=>2,'name'=>'Bun','planned_quantity'=>12,'produced_quantity'=>12,'remaining_quantity'=>0]];
$view['saved_plan']=['exists'=>true,'revision'=>3,'sectors'=>[['name'=>'Bakery','categories'=>[['name'=>'Bread','lines'=>$planLines]]]],'categories'=>[]];
foreach (['pl','en','fr','nl','it'] as $lang){
 $view['translations']=json_decode(file_get_contents(__DIR__.'/../src/core/I18n/translations/page/'.$lang.'/inventory.json'),true,512,JSON_THROW_ON_ERROR);
 $html=$twig->render('inventory/index.twig',$view);
 $check(str_contains($html,$view['translations']['title']),'Inventory renders '.$lang);
 $check(str_contains($html,'data-product="2" data-name="Zero" data-stock="0" data-initialized="2026-09-01" data-movement="45" data-supported="1" hidden'),'Zero hidden from first render '.$lang);
 $check(str_contains($html,'value="" placeholder='),'Unknown requires explicit input '.$lang);
 $check(str_contains($html,'data-product="3" data-name="Unknown" data-stock="0" data-initialized="" data-movement="45" data-supported="1" hidden'),'Uninitialized hidden from first render '.$lang);
 $check(str_contains($html,'data-product="5" data-name="Legacy placeholder" data-stock="999" data-initialized="" data-movement="45" data-supported="1" hidden'),'Unverified legacy999 hidden '.$lang);
 $check(str_contains($html,'<b>3</b>'),'Hidden count includes zero and both unknown states '.$lang);
 if($lang==='pl')file_put_contents('/tmp/kitchen-inventory-fixture.html',$html);
 $view['translations']=json_decode(file_get_contents(__DIR__.'/../src/core/I18n/translations/page/'.$lang.'/production_list.json'),true,512,JSON_THROW_ON_ERROR);
 $html=$twig->render('production_list/receive.twig',$view);
 $check(str_contains($html,'value="15"'),'Receipt prefills only remaining '.$lang);
 $check(str_contains($html,'expected_produced_quantity'),'Receipt carries concurrency guard '.$lang);
 if($lang==='pl')file_put_contents('/tmp/kitchen-receipt-fixture.html',$html);
}
echo "Kitchen inventory: {$checks} checks passed\n";
