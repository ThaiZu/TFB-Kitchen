<?php
require __DIR__.'/../vendor/autoload.php';
$_SERVER['SERVER_NAME']='localhost';$_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']='/kitchen/deliveries';
foreach (['JWT_SECRET','JWT_ISSUER','JWT_ACCESS_TOKEN_EXPIRY','JWT_REFRESH_TOKEN_EXPIRY','DEFAULT_COUNTRY','CURRENCY','CURRENCY_SYMBOL','APP_NAME','APP_DESC'] as $key) $_ENV[$key]=getenv($key) ?: ($key==='JWT_SECRET'?'test-only-delivery-secret':'');
require __DIR__.'/../config/app.php';require __DIR__.'/../src/core/Support/functions.php';
use App\Kitchen\app\Services\Material\DeliveryService;
use App\Kitchen\app\Repositories\Material\DeliveryRepository;
use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\core\Support\GlobalRegistry;
use App\Kitchen\core\Support\ShiftSession;

$checks=0;$expect=function($value,$message)use(&$checks){++$checks;if(!$value)throw new RuntimeException($message);};
$order=['id'=>40,'supplier_name'=>'Supplier <script>alert(1)</script>','delivery_date'=>'2026-11-05','status'=>'IN_TRANSIT','can_receive'=>true,'shipment_revision'=>3,'now'=>'2026-11-04T11:00','lines'=>[
    ['key'=>str_repeat('a',64),'name'=>'Croissant <script>alert(1)</script>','sku'=>'CR','ordered'=>3,'declared'=>2,'quantity'=>2,'pack_size'=>12,'unit'=>'pcs','mapped'=>true],
    ['key'=>str_repeat('b',64),'name'=>'Gift','sku'=>'CR','ordered'=>0,'declared'=>1,'quantity'=>1,'pack_size'=>12,'unit'=>'pcs','mapped'=>true],
]];
$repository=new class($order) extends DeliveryRepository {
    public array $writes=[];public bool $fail=false;public bool $networkFailure=false;
    public function __construct(public array $order){}
    public function recent(int $shop):array{return ['success'=>!$this->fail,'data'=>[['id'=>40,'supplier_name'=>'Supplier','delivery_date'=>'2026-11-05','status'=>'IN_TRANSIT','product_count'=>1,'pack_count'=>3]]];}
    public function detail(int $shop,int $order):array{return ['success'=>!$this->fail,'data'=>$this->order];}
    public function receive(int $shop,int $order,array $payload):array{$this->writes[]=[$shop,$order,$payload];if($this->networkFailure)return ['success'=>false,'code'=>0];return ['success'=>true,'data'=>['success_insert_delivery'=>true,'success_recalculate_costs'=>true]];}
};
GlobalRegistry::set('user',['shop_id'=>7,'device_id'=>16]);GlobalRegistry::set('lang_code','pl');
$service=new DeliveryService($repository);
$expect($service->screen()['available'],'List available');
$expect(!$service->screen(40)['has_shift'],'Unsigned worker blocked');
$expect($service->receive(40,[])['code']===403&&$repository->writes===[],'No unsigned write');
$_COOKIE[ShiftSession::COOKIE]=ShiftSession::rules()->sign(11,'Ada',time()-60,time(),JWT_SECRET_KEY,7,date('Y-m-d'));
$expect($service->screen(40)['has_shift'],'Signed shift accepted');
$result=$service->receive(40,['shipment_revision'=>3,'delivered_on'=>'2026-11-04T10:00','quantities'=>['a'=>'2'],'id_employee'=>999,'materials'=>[['pack_price'=>999]]]);
$expect($result['success'],'Receipt result passed through');
$expect(!isset($repository->writes[0][2]['id_employee'])&&!isset($repository->writes[0][2]['materials']),'Client actor and prices never forwarded');
$repository->networkFailure=true;$expect($service->receive(40,[])['code']===502,'Transport failure is uncertain, never a validation failure');$repository->networkFailure=false;
$repository->fail=true;$expect(!$service->screen()['available'],'Read failure distinguished from empty list');$repository->fail=false;
$dir=getenv('KITCHEN_DELIVERY_FIXTURE_DIR')?:sys_get_temp_dir().'/kitchen-deliveries';if(!is_dir($dir))mkdir($dir,0777,true);
$container=require __DIR__.'/../src/core/Container/Container.php';
$base=['entry_employees'=>[['id'=>11,'name'=>'Ada','initials'=>'A']]];
foreach(['pl','en','fr','nl','it'] as $lang){
    GlobalRegistry::set('lang_code',$lang);$_GET['url']='deliveries';
    foreach(['index','receive'] as $view){
        ob_start();$container->get(Controller::class)->view('deliveries/'.$view,$base+$service->screen($view==='receive'?40:null));$html=ob_get_clean();
        $dom=new DOMDocument();@$dom->loadHTML($html);$xpath=new DOMXPath($dom);
        $expect($xpath->query('//a[@href="'.ROOT.'/deliveries"]')->length>=2,'Delivery navigation in '.$lang);
        $expect(!str_contains($html,'<script>alert(1)</script>'),'Escaped supplier and product names');
        $expect(!preg_match('/<h1>\s*<\/h1>/',$html),'Localized title');
        if($lang==='pl')file_put_contents($dir.'/'.$view.'.html',$html);
    }
}
GlobalRegistry::set('lang_code','pl');
foreach(['empty'=>['available'=>true,'orders'=>[],'has_shift'=>true], 'unavailable'=>['available'=>false,'orders'=>[],'has_shift'=>true], 'closed'=>['available'=>true,'order'=>array_replace($order,['can_receive'=>false,'status'=>'DELIVERED','delivered_on'=>'2026-11-04 11:00:00']),'has_shift'=>true], 'anonymous'=>['available'=>true,'order'=>$order,'has_shift'=>false]] as $name=>$data){
    ob_start();$container->get(Controller::class)->view('deliveries/'.(isset($data['order'])?'receive':'index'),$base+$data);$html=ob_get_clean();
    file_put_contents($dir.'/'.$name.'.html',$html);
    if(isset($data['order']))$expect(!str_contains($html,'id="dlSave"'),'Read-only delivery has no save button');
}
$dispatcher=FastRoute\simpleDispatcher(require __DIR__.'/../src/core/Bootstrap/routes.php');
foreach([['GET','/deliveries'],['GET','/deliveries/40'],['POST','/ajax/deliveries/40/receive']] as [$method,$path])$expect($dispatcher->dispatch($method,$path)[0]===FastRoute\Dispatcher::FOUND,'Registered route '.$path);
echo "Kitchen deliveries: $checks checks PASS\n";
