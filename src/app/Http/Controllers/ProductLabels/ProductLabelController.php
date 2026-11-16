<?php
namespace App\Kitchen\app\Http\Controllers\ProductLabels;
use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\core\Http\ApiClient;
use App\Kitchen\core\Support\{GlobalRegistry,ShiftSession};
use Symfony\Component\HttpFoundation\JsonResponse;
final class ProductLabelController extends Controller
{
 public function __construct(private ApiClient $api) {}
 public function index():void {header('Cache-Control: private, no-store');$this->view('product_labels/index',[]);}
 public function catalog():JsonResponse {return $this->data('catalog');}
 public function jobs():JsonResponse {return $this->data('jobs');}
 public function create():JsonResponse {return $this->data('create');}
 public function reprint():JsonResponse {return $this->data('reprint');}
 private function data(string $action):JsonResponse
 {
  header('Cache-Control: private, no-store');$user=GlobalRegistry::get('user')??[];$shop=(int)($user['shop_id']??0);
  if($shop<1||($_SERVER['HTTP_X_REQUESTED_WITH']??'')!=='XMLHttpRequest'||($_SERVER['HTTP_SEC_FETCH_SITE']??'')==='cross-site')return $this->json(['success'=>false],403);
  $path='/shops/'.$shop.'/product-label-printing/';
  try{
   if(in_array($action,['catalog','jobs'],true))$r=$this->api->get($path.$action);
   else{
    if(stripos($_SERVER['CONTENT_TYPE']??'','application/json')!==0)return $this->json(['success'=>false],403);
    $raw=file_get_contents('php://input',false,null,0,65537);if(strlen($raw)>65536)return $this->json(['success'=>false],413);
    $input=json_decode($raw,true);if(!is_array($input)||array_is_list($input))return $this->json(['success'=>false],422);
    $shift=ShiftSession::current($shop);if(!$shift)return $this->json(['success'=>false,'reason'=>'shift'],403);
    $input['employee_id']=(int)$shift['id'];$input['lang']=GlobalRegistry::get('lang_code')??'fr';
    if($action==='reprint'){$job=$input['job_id']??null;if(!is_string($job)||!preg_match('/^[1-9][0-9]{0,19}$/D',$job))return $this->json(['success'=>false],422);$r=$this->api->post($path.'jobs/'.$job.'/reprint',['employee_id'=>$input['employee_id'],'request_id'=>$input['request_id']??null]);}
    else $r=$this->api->post($path.'create',$input);
   }
   $code=(int)($r['code']??502);if($code<400||$code>599)$code=502;
   return $this->json(['success'=>(bool)($r['success']??false),'data'=>$r['data']??null],($r['success']??false)?200:$code);
  }catch(\Throwable $e){error_log('Product label request failed: '.get_class($e));return $this->json(['success'=>false],502);}
 }
}
