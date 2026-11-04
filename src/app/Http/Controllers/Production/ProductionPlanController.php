<?php
namespace App\Kitchen\app\Http\Controllers\Production;
use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\app\Services\Production\ProductionDayService;
final class ProductionPlanController extends Controller
{
    public function __construct(private ProductionDayService $service) {}
    public function index(): void
    {
        header('Cache-Control: private, no-store');$offset=$_GET['offset']??'0';
        if(!is_string($offset)||!preg_match('/^[012]$/D',$offset)){http_response_code(422);$this->view('production_plan/index',['available'=>false,'offset'=>0,'model'=>[]]);return;}
        $result=$this->service->plan((int)$offset);
        $this->view('production_plan/index',['available'=>(bool)($result['success']??false),'offset'=>(int)$offset,'model'=>$result['data']??[]]);
    }
}
