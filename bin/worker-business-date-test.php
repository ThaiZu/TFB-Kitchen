<?php
/** Offline regression: header, PIN login, roster and production share the API shop date. */
require __DIR__.'/../vendor/autoload.php';
use App\Kitchen\core\Support\{ShopClock,ShiftSession,GlobalRegistry};
use App\Kitchen\core\Http\ApiClient;
use App\Kitchen\app\Services\Checklist\{ChecklistService,ChecklistFocusService};
use App\Kitchen\app\Http\Controllers\Checklist\ChecklistController;
use App\Kitchen\app\Services\Production\ProductionDayService;
use App\Kitchen\app\Repositories\Production\ProductionDayRepository;

define('JWT_SECRET_KEY','worker-clock-fixture-only');
$checks=0;
function clockExpect(bool $condition,string $label): void {global $checks;++$checks;if(!$condition)throw new RuntimeException($label);}
$shopDate=(new DateTimeImmutable('today'))->modify('+1 day')->format('Y-m-d');
$client=new class($shopDate) extends ApiClient {
    public int $clockCalls=0;public bool $available=true;public int $responseShop=2;
    public function __construct(public string $today){}
    public function get($endpoint,$decodeJson=true){
        if(str_ends_with($endpoint,'/business-clock')){++$this->clockCalls;return ['success'=>$this->available,'data'=>['shop_id'=>$this->responseShop,'today'=>$this->today,'timezone'=>'Europe/Brussels']];}
        if(str_contains($endpoint,'/production-day'))return ['success'=>true,'data'=>['today'=>$this->today,'date'=>$this->today,'status'=>'NOT_OPEN','products'=>[]]];
        return ['success'=>false];
    }
};
$checklist=new class extends ChecklistService {
    public array $dates=[];
    public function __construct(){}
    public function shopId():int{return 2;}
    public function roster(array $employees):array{return ['mode'=>'ready','list'=>$employees];}
    public function verifyPin(int $employeeId,string $pin):array{return ['success'=>$employeeId===24&&$pin==='1234','name'=>'Fixture worker'];}
    public function isScheduledEmployee(int $id,string $date):bool{$this->dates[]=$date;return true;}
    public function getEmployeesForShop(?string $date=null):array{$this->dates[]=$date;return [['id'=>24,'name'=>'Fixture worker','on_schedule'=>true]];}
};
ShopClock::initialize($client);GlobalRegistry::set('user',['shop_id'=>2,'device_id'=>16]);
$_POST=['employee_id'=>24,'pin'=>'1234'];
$controller=new ChecklistController($checklist,new ChecklistFocusService());
clockExpect($controller->openShift()->getStatusCode()===200,'PIN login succeeds');
clockExpect($checklist->dates===[$shopDate],'Schedule validation uses the shop day, not the PHP day');
clockExpect(ShiftSession::current(2,$shopDate)!==null,'Cookie is signed for the shop day');
clockExpect(ShiftSession::current(2,date('Y-m-d'))===null,'Fixture reproduces different server date');
clockExpect(ShiftSession::current(2,ShopClock::today(2))!==null,'Header sees the same active worker');
clockExpect((new ProductionDayService(new ProductionDayRepository($client)))->screen()['has_shift'],'Production day sees the same active worker');
clockExpect($controller->tabletWorkerRoster()->getStatusCode()===200&&end($checklist->dates)===$shopDate,'Roster uses the same shop day');
$staffRepository=new class extends \App\Kitchen\app\Repositories\Staff\StaffRepository {
 public array $calls=[];public function __construct(){}public function getSchedule(int $shop,string $date):?array{$this->calls[]=[$shop,$date];return [];}
};
(new \App\Kitchen\app\Services\Staff\StaffService($staffRepository))->getEmployees();
clockExpect($staffRepository->calls===[[2,$shopDate]],'Default staff schedule uses the same shop day');
clockExpect($client->clockCalls===1,'Clock is fetched at most once per request');
$client->available=false;ShopClock::initialize($client);
$cookie=$_COOKIE[ShiftSession::COOKIE];
clockExpect($controller->openShift()->getStatusCode()===503,'Clock outage never signs with the server date');
clockExpect($_COOKIE[ShiftSession::COOKIE]===$cookie,'Clock outage preserves the existing employee cookie');
$client->available=true;$client->responseShop=99;ShopClock::initialize($client);
clockExpect(ShopClock::today(2)===null,'Another shop clock cannot be used');
$client->responseShop=2;$client->today='2026-02-31';ShopClock::initialize($client);
clockExpect(ShopClock::today(2)===null,'Malformed API day is rejected');
ShopClock::remember(2,$shopDate);
clockExpect(ShiftSession::current(2,$shopDate)!==null,'Read-only checks do not extend or replace the signed worker');
echo "Worker business date: $checks checks PASS\n";
