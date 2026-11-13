<?php
require __DIR__.'/../vendor/autoload.php';
use App\Kitchen\core\Http\ApiClient;
use App\Kitchen\app\Repositories\Checklist\ChecklistRepository;
use App\Kitchen\app\Services\Checklist\ChecklistService;
use App\Kitchen\app\Services\Staff\StaffService;
use App\Kitchen\core\Support\GlobalRegistry;
$api=new class extends ApiClient {
    public array $response=[]; public array $sent=[]; public array $completion=[];
    public function __construct() {}
    public function postMultipart(string $endpoint, array $fields, array $files = []) { $this->completion=[$endpoint,$fields,$files]; return ['success'=>true]; }
    public function post($endpoint,$data) { $this->sent=[$endpoint,$data]; return $this->response; }
};
$repo=new ChecklistRepository($api);
$staff=(new ReflectionClass(StaffService::class))->newInstanceWithoutConstructor();
$service=new ChecklistService($repo,$staff);
GlobalRegistry::set('user',['shop_id'=>2]);
$check=static function($actual,$expected): void { if($actual!==$expected)throw new RuntimeException('Unexpected result'); };
$api->response=['success'=>true,'data'=>['success'=>true,'name'=>'Anna']];
$check($service->verifyPin(7,'0080'),['success'=>true,'message'=>'ok','name'=>'Anna']);
$check($api->sent,['/kitchen/employees/verify-pin',['employee_id'=>7,'pin'=>'0080']]);
foreach(['invalid_pin','pin_locked','employee_not_in_shop'] as $reason) {
 $api->response=['success'=>false,'data'=>['success'=>false,'message'=>$reason]];
 $check($service->verifyPin(7,'0080'),['success'=>false,'message'=>$reason]);
}
$api->response=['success'=>false,'code'=>503,'data'=>[]];
$check($service->verifyPin(7,'0080'),['success'=>false,'message'=>'verification_unavailable']);
$api->response=['success'=>true,'data'=>['success'=>true,'name'=>'Anna']];
$photo=['tmp_name'=>'/unused-test-image','error'=>UPLOAD_ERR_OK];
$check($service->completeTask(1206,7,'0080','2026-11-13','test',$photo)['success'],true);
$check($api->completion[0],'/employees/7/tasks/1206/mark-as-done');
$check($api->completion[2],['photo'=>$photo]);
$api->completion=[];
$api->response=['success'=>false,'data'=>['message'=>'invalid_pin']];
$check($service->completeTask(1206,7,'0080','2026-11-13','test',$photo)['success'],false);
$check($api->completion,[]);
echo "PASS: 11 Kitchen PIN integration assertions\n";
