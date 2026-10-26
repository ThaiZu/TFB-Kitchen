<?php
require __DIR__.'/../vendor/autoload.php';
use App\Kitchen\app\Repositories\Production\ProductionDayRepository;
use App\Kitchen\app\Services\Production\ProductionDayService;
use App\Kitchen\core\Http\ApiClient;
use App\Kitchen\core\Support\{GlobalRegistry,ShiftSession};
define('JWT_SECRET_KEY','kitchen-day-fixture-only');
$fixtureDir=getenv('KITCHEN_DAY_FIXTURE_DIR')?:sys_get_temp_dir().'/kitchen-day-mvp';
if(!is_dir($fixtureDir))mkdir($fixtureDir,0775,true);
$today=(new DateTimeImmutable('now',new DateTimeZone('Europe/Warsaw')))->format('Y-m-d');
$make=static fn(int $id,string $name,int $stock,string $life,bool $mandatory=false):array=>['id_product'=>$id,'name'=>$name,'stock_quantity'=>number_format($stock,4,'.',''),'in_stock'=>number_format($stock,4,'.',''),'inventory_initialized_at'=>'2026-10-01 06:00:00','last_movement_id'=>(string)(20+$id),'supported'=>true,'is_active'=>1,'sale_enabled'=>1,'shelf_life_category'=>$life,'is_mandatory_assortment'=>$mandatory,'default_opening'=>$stock>0||$mandatory,'previous_carryover'=>null];
$sheet=['shop_id'=>1,'date'=>$today,'today'=>$today,'timezone'=>'Europe/Warsaw','status'=>'NOT_OPEN','revision'=>0,'read_only'=>false,'opening'=>null,'closing'=>null,'previous_date'=>null,'products'=>[$make(1,'Croissant <SSL>',10,'SSL'),$make(2,'Mandatory cake',0,'MSL',true),$make(3,'Long life juice',4,'LSL'),$make(4,'Extra bun',0,'SSL'),$make(5,'Late tart',0,'MSL')],'waste_reasons'=>[['key'=>'expiration','label'=>'Upływ terminu ważności'],['key'=>'damage','label'=>'Uszkodzenie'],['key'=>'quality','label'=>'Jakość'],['key'=>'other_waste','label'=>'Inne']]];
$api=new class($sheet) extends ApiClient {
 public array $calls=[];
 public function __construct(private array $sheet) {}
 public function get($url,$decode=true){$this->calls[]=['GET',$url];return ['success'=>true,'data'=>$this->sheet];}
 public function post($url,$payload){$this->calls[]=['POST',$url,$payload];return ['success'=>true,'data'=>['status'=>'OPEN','revision'=>1]];}
};
$checks=0;$check=static function(bool $ok,string $label)use(&$checks):void{++$checks;if(!$ok)throw new RuntimeException($label);};
GlobalRegistry::set('user',['shop_id'=>1,'device_id'=>7]);$service=new ProductionDayService(new ProductionDayRepository($api));
$check($service->screen()['available']===true,'Screen uses shop API');$check($service->screen()['has_shift']===false,'No writes without employee shift');
$check($service->command('open',['date'=>$today])['code']===403,'Write denied before API without shift');
$_COOKIE[ShiftSession::COOKIE]=ShiftSession::rules()->sign(9,'Employee',time(),time(),JWT_SECRET_KEY,1,$today);
$check($service->screen()['has_shift']===true,'Signed matching shift permits edits');
$payload=['request_id'=>'opening-test-key','date'=>$today,'expected_revision'=>0,'lines'=>[]];$service->command('open',$payload);
$check(end($api->calls)===['POST','/shops/1/production-day/open',$payload],'Shop resolved from server context; request preserved for replay');
GlobalRegistry::set('user',['shop_id'=>2,'device_id'=>8]);$check($service->command('open',$payload)['code']===403,'Other shop cannot reuse current shift');GlobalRegistry::set('user',['shop_id'=>1,'device_id'=>7]);
$loader=new Twig\Loader\ChainLoader([new Twig\Loader\ArrayLoader(['layouts/base.twig'=>'<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{font-family:Arial;margin:0;padding:16px;box-sizing:border-box}*{box-sizing:border-box}</style>{% block head %}{% endblock %}</head><body>{% block content %}{% endblock %}</body></html>']),new Twig\Loader\FilesystemLoader(__DIR__.'/../src/app/Views')]);
$twig=new Twig\Environment($loader,['strict_variables'=>true]);
foreach(['pl','en','fr','it','nl'] as $lang){$t=json_decode(file_get_contents(__DIR__.'/../src/core/I18n/translations/page/'.$lang.'/production_day.json'),true,512,JSON_THROW_ON_ERROR);$html=$twig->render('production_day/index.twig',['ROOT'=>'/kitchen','translations'=>$t,'available'=>true,'has_shift'=>true,'view'=>'opening','sheet'=>$sheet]);$check(str_contains($html,'production-day.js') && str_contains($html,'pd-dialog'),'Three-view UI renders '.$lang);$check(!str_contains($html,'<SSL>'),'Product names encoded inside JSON seed');if($lang==='pl')file_put_contents($fixtureDir.'/ui.html',$html);}
$dispatcher=FastRoute\simpleDispatcher(require __DIR__.'/../src/core/Bootstrap/routes.php');
foreach([['GET','/production-day'],['GET','/production-day/data'],['POST','/production-day/open'],['POST','/production-day/adjust'],['POST','/production-day/close']] as [$method,$path])$check($dispatcher->dispatch($method,$path)[0]===FastRoute\Dispatcher::FOUND,'Route '.$path);
echo "Kitchen production day: $checks checks PASS\n";
