<?php
namespace App\Kitchen\app\Http\Controllers\Material;
use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\app\Services\Material\DeliveryService;
use Symfony\Component\HttpFoundation\JsonResponse;

final class DeliveryController extends Controller
{
    public function __construct(private DeliveryService $deliveries) {}
    public function index(): void { header('Cache-Control: no-store'); $this->view('deliveries/index',$this->deliveries->screen()); }
    public function detail(int $id): void { header('Cache-Control: no-store'); $this->view('deliveries/receive',$this->deliveries->screen($id)); }
    public function receive(int $id): JsonResponse
    {
        header('Cache-Control: no-store');
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')!=='XMLHttpRequest'
            || stripos($_SERVER['CONTENT_TYPE'] ?? '','application/json')!==0
            || ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')==='cross-site') {
            return $this->json(['success'=>false,'reason'=>'forbidden'],403);
        }
        $input=json_decode(file_get_contents('php://input'),true);
        if (!is_array($input)) return $this->json(['success'=>false,'reason'=>'INVALID_DELIVERY_LINES'],422);
        $result=$this->deliveries->receive($id,$input);
        return $this->json($result,($result['success'] ?? false) ? 200 : (int)($result['code'] ?? 502));
    }
}
