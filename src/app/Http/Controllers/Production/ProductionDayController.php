<?php
namespace App\Kitchen\app\Http\Controllers\Production;
use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\app\Services\Production\ProductionDayService;
use Symfony\Component\HttpFoundation\JsonResponse;
final class ProductionDayController extends Controller
{
    public function __construct(private ProductionDayService $service) {}
    public function legacy(): void {header('Location: '.ROOT.'/production-day',true,302);}
    public function index(): void
    {
        header('Cache-Control: private, no-store');
        $date=isset($_GET['date'])&&is_string($_GET['date'])?$_GET['date']:null;
        $screen=$this->service->screen($date);$screen['view']=in_array($_GET['view']??'', ['opening','stock','prepare','closing'],true)?$_GET['view']:($screen['sheet']['default_view']??'opening');
        $this->view('production_day/index',$screen);
    }
    public function data(): JsonResponse
    {
        if (($_SERVER['HTTP_X_REQUESTED_WITH']??'')!=='XMLHttpRequest' || ($_SERVER['HTTP_SEC_FETCH_SITE']??'')==='cross-site') return $this->json(['success'=>false],403);
        $screen=$this->service->screen(isset($_GET['date'])&&is_string($_GET['date'])?$_GET['date']:null);
        return $this->json(['success'=>$screen['available'],'data'=>$screen['sheet']??null],$screen['available']?200:503);
    }
    public function sectors(): JsonResponse {return $this->json($this->service->sectors());}
    public function sector(): JsonResponse
    {
        if(($_SERVER['HTTP_X_REQUESTED_WITH']??'')!=='XMLHttpRequest' || ($_SERVER['HTTP_SEC_FETCH_SITE']??'')==='cross-site' || stripos($_SERVER['CONTENT_TYPE']??'','application/json')!==0)return $this->json(['success'=>false],403);
        $input=json_decode(file_get_contents('php://input'),true);
        if(!is_array($input)||array_keys($input)!==['sector_id'])return $this->json(['success'=>false],422);
        $result=$this->service->selectSector($input['sector_id']);return $this->json($result,$result['code']??200);
    }
    public function uploadPhoto(): JsonResponse
    {
        if(($_SERVER['HTTP_X_REQUESTED_WITH']??'')!=='XMLHttpRequest'||($_SERVER['HTTP_SEC_FETCH_SITE']??'')==='cross-site'||stripos($_SERVER['CONTENT_TYPE']??'','application/json')!==0)return $this->json(['success'=>false],403);
        $raw=file_get_contents('php://input',false,null,0,7340033);if(strlen($raw)>7340032)return $this->json(['success'=>false],413);$input=json_decode($raw,true);if(!is_array($input)||array_is_list($input))return $this->json(['success'=>false],422);
        $result=$this->service->uploadPhoto($input);return $this->json($result,($result['success']??false)?200:max(400,(int)($result['code']??502)));
    }
    public function command(string $operation): JsonResponse
    {
        if (($_SERVER['HTTP_X_REQUESTED_WITH']??'')!=='XMLHttpRequest' || stripos($_SERVER['CONTENT_TYPE']??'','application/json')!==0
            || ($_SERVER['HTTP_SEC_FETCH_SITE']??'')==='cross-site') return $this->json(['success'=>false],403);
        $raw=file_get_contents('php://input',false,null,0,2097153);
        if (strlen($raw)>2097152) return $this->json(['success'=>false],413);
        $input=json_decode($raw,true);
        if (!is_array($input) || array_is_list($input)) return $this->json(['success'=>false],422);
        $result=$this->service->command($operation,$input);
        return $this->json($result,($result['success']??false)?200:max(400,(int)($result['code']??502)));
    }
}
