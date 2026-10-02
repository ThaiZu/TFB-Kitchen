<?php
namespace App\Kitchen\app\Http\Controllers\Inventory;
use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\app\Services\Inventory\InventoryService;
use Symfony\Component\HttpFoundation\JsonResponse;
final class InventoryController extends Controller
{
    public function __construct(private InventoryService $service) {}
    public function index(): void { $this->view('inventory/index',$this->service->snapshot()); }
    public function count(): JsonResponse
    {
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest'
            || stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0
            || ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') return $this->json(['success'=>false,'reason'=>'forbidden'],403);
        $input=json_decode(file_get_contents('php://input'),true);
        if (!is_array($input)) return $this->json(['success'=>false,'reason'=>'invalid_input'],422);
        $result=$this->service->count($input);
        $status=($result['success'] ?? false) ? 200 : max(400,(int)($result['code'] ?? 502));
        return $this->json(['success'=>(bool)($result['success'] ?? false),'reason'=>$result['reason'] ?? null],$status);
    }
}
