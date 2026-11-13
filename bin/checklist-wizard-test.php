<?php
/** Render the real checklist view; no API/database writes. */
require __DIR__.'/../vendor/autoload.php';
$_SERVER['SERVER_NAME']='localhost';$_SERVER['REQUEST_METHOD']='GET';$_GET['url']='checklists';
foreach(['JWT_SECRET','JWT_ISSUER','JWT_ACCESS_TOKEN_EXPIRY','JWT_REFRESH_TOKEN_EXPIRY','DEFAULT_COUNTRY','CURRENCY','CURRENCY_SYMBOL','APP_NAME','APP_DESC'] as $key)$_ENV[$key]='';
require __DIR__.'/../config/app.php';require __DIR__.'/../src/core/Support/functions.php';
use App\Kitchen\core\Support\{GlobalRegistry,DeviceMode,ShopClock};
GlobalRegistry::set('user',['shop_id'=>2,'device_id'=>7]);DeviceMode::rules()->applyConfig(null);ShopClock::remember(2,'2026-11-13');
$container=require __DIR__.'/../src/core/Container/Container.php';$controller=$container->get(\App\Kitchen\app\Http\Controllers\Controller::class);
$checks=0;$expect=function($v,$why)use(&$checks){$checks++;if(!$v)throw new RuntimeException($why);};
$tasks=[
 ['task_id'=>10,'name'=>'Already done','status'=>'DONE','is_mandatory'=>1,'requires_photo'=>0,'execution_time'=>'06:00:00','completed_at'=>'2026-11-13 06:01:00','completed_by'=>'Anna','note'=>''],
 ['task_id'=>11,'name'=>'Clean counter <test>','status'=>'PENDING','is_mandatory'=>1,'requires_photo'=>0,'execution_time'=>'06:10:00','completed_at'=>null,'completed_by'=>null,'note'=>''],
 ['task_id'=>12,'name'=>'Photo of oven','status'=>'PENDING','is_mandatory'=>0,'requires_photo'=>1,'execution_time'=>'06:20:00','completed_at'=>null,'completed_by'=>null,'note'=>''],
 ['task_id'=>13,'name'=>'Check fridge','status'=>'PENDING','is_mandatory'=>1,'requires_photo'=>0,'execution_time'=>'06:30:00','completed_at'=>null,'completed_by'=>null,'note'=>''],
];
$dir=getenv('CHECKLIST_FIXTURE_DIR')?:sys_get_temp_dir().'/kitchen-checklist-wizard';if(!is_dir($dir))mkdir($dir,0777,true);
foreach(['pl','en','fr','nl','it'] as $lang)foreach([true,false] as $shift){
 GlobalRegistry::set('lang_code',$lang);
 $data=['selected_date'=>'2026-11-13','selected_checklist_id'=>101,'checklists'=>[['id'=>101,'name'=>'Opening checklist','tasks_done'=>1,'tasks_total'=>4,'execution_time'=>'06:00:00'],['id'=>102,'name'=>'Closing checklist','tasks_done'=>0,'tasks_total'=>1,'execution_time'=>'17:00:00']],'progress'=>['checklist'=>['id'=>101,'name'=>'Opening checklist'],'tasks'=>$tasks,'summary'=>['total'=>4,'done'=>1,'not_done'=>3]],'today'=>'2026-11-13','employees'=>[['id'=>7,'name'=>'Anna','initials'=>'A','on_schedule'=>true]],'roster_mode'=>'scheduled','missing_api'=>null,'auto_focus'=>false,'focused_name'=>'Opening checklist','shift'=>$shift?['name'=>'Anna','initials'=>'A','remaining'=>3600]:null,'employee_mode'=>$shift,'employee_no_tasks'=>false,'shift_entry_required'=>false];
 ob_start();$controller->view('checklist/index',$data);$html=ob_get_clean();
 $expect(str_contains($html,'id="checklist-wizard"'),'Selected checklist has a wizard');$expect(!str_contains($html,'id="cw-start"'),'No extra start decision');$expect(str_contains($html,'id="cw-task-list" hidden'),'Full task list is hidden before initialization');$expect(substr_count($html,'data-cw-task-id=')===4,'Only selected checklist tasks are in this wizard');$expect(str_contains($html,'Clean counter &lt;test&gt;'),'Task names are escaped');$expect(!str_contains($html,'wizard_progress"'),'New labels are translated');
 file_put_contents($dir.'/checklist-'.$lang.'-'.($shift?'shift':'pin').'.html',$html);
 $data['selected_checklist_id']=null;$data['progress']=null;ob_start();$controller->view('checklist/index',$data);$all=ob_get_clean();$expect(!str_contains($all,'id="checklist-wizard"'),'All-checklists page never combines tasks into a wizard');$expect(str_contains($all,'checklist_id=101')&&str_contains($all,'checklist_id=102'),'Separate checklist links remain');
 $data['selected_checklist_id']=101;$data['progress']=['checklist'=>['id'=>101,'name'=>'Opening'],'tasks'=>[]];ob_start();$controller->view('checklist/index',$data);$empty=ob_get_clean();$expect(!str_contains($empty,'id="checklist-wizard"'),'Empty checklist has no blank wizard');
}
// Runtime variants model subsequent authoritative reads after each task save.
GlobalRegistry::set('lang_code','pl');
foreach(['initial','done11','done12','reported13'] as $variant)foreach([true,false] as $hasShift){
 $data['checklists']=[['id'=>101,'name'=>'Opening checklist','tasks_done'=>1,'tasks_total'=>4,'execution_time'=>'06:00:00'],['id'=>102,'name'=>'Closing checklist','tasks_done'=>0,'tasks_total'=>1,'execution_time'=>'17:00:00']];$data['selected_checklist_id']=101;$data['shift']=$hasShift?['name'=>'Anna','initials'=>'A','remaining'=>3600]:null;
 $rows=$tasks;foreach($rows as &$task){if($task['task_id']===11&&$variant!=='initial')$task['status']='DONE';if($task['task_id']===12&&in_array($variant,['done12','reported13'],true))$task['status']='DONE';if($task['task_id']===13&&$variant==='reported13'){$task['status']='FAILED';$task['note']='Fridge unavailable';}}unset($task);
 $data['progress']=['checklist'=>['id'=>101,'name'=>'Opening checklist'],'tasks'=>$rows,'summary'=>['total'=>4,'done'=>count(array_filter($rows,fn($t)=>$t['status']==='DONE')),'not_done'=>0]];
 ob_start();$controller->view('checklist/index',$data);file_put_contents($dir.'/runtime-'.$variant.'-'.($hasShift?'shift':'pin').'.html',ob_get_clean());
}
$data['selected_checklist_id']=102;$data['progress']=['checklist'=>['id'=>102,'name'=>'Closing checklist'],'tasks'=>[array_merge($tasks[1],['task_id'=>30,'name'=>'Close the store'])],'summary'=>['total'=>1,'done'=>0,'not_done'=>1]];
ob_start();$controller->view('checklist/index',$data);file_put_contents($dir.'/runtime-other.html',ob_get_clean());
$data['selected_checklist_id']=null;$data['progress']=null;ob_start();$controller->view('checklist/index',$data);file_put_contents($dir.'/runtime-overview.html',ob_get_clean());
// Exercise entry routing and pending filtering through the real controller.
$service = new class extends \App\Kitchen\app\Services\Checklist\ChecklistService {
 public function __construct() {}
 public function shopId(): int { return 2; }
 public function getChecklistsForShop(string $date): array { return [
  ['id'=>101,'name'=>'Pending','tasks_done'=>1,'tasks_total'=>2,'execution_time'=>'06:00'],
  ['id'=>103,'name'=>'Completed','tasks_done'=>2,'tasks_total'=>2,'execution_time'=>'07:00'],
  ['id'=>104,'name'=>'Empty','tasks_done'=>0,'tasks_total'=>0,'execution_time'=>'08:00']
 ]; }
 public function getEmployeesForShop(?string $date=null): array { return []; }
 public function roster(array $employees): array { return ['list'=>[],'mode'=>'scheduled','missing'=>null]; }
 public function getChecklistProgress(int $checklistId,string $date): array { return ['checklist'=>['id'=>$checklistId,'name'=>'Selected'],'tasks'=>[]]; }
};
$entry = new \App\Kitchen\app\Http\Controllers\Checklist\ChecklistController($service);
$_GET=[];ob_start();$entry->index();$overview=ob_get_clean();
$expect(!str_contains($overview,'id="checklist-wizard"'),'Entry never auto-selects a checklist');
$expect(str_contains($overview,'checklist_id=101'),'Pending checklist is available');
$expect(!str_contains($overview,'checklist_id=103')&&!str_contains($overview,'checklist_id=104'),'Completed and empty checklists are omitted');
$_GET=['checklist_id'=>999];ob_start();$entry->index();$invalid=ob_get_clean();
$expect(str_contains($invalid,'checklist_id=101'),'Unknown checklist returns to pending overview');
echo "Checklist wizard rendering: $checks checks PASS (five languages, shift/PIN, separate checklists, empty state).\n";
