<?php
namespace App\Kitchen\app\Http\Controllers\Inventory;
use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\app\Services\Inventory\InventoryService;
use App\Kitchen\app\Services\Inventory\WasteReportService;
use App\Kitchen\app\Services\Inventory\CarryoverSettlementService;
use Symfony\Component\HttpFoundation\JsonResponse;
final class InventoryController extends Controller
{
    public function __construct(
        private InventoryService $service,
        private WasteReportService $wasteReportService,
        private CarryoverSettlementService $carryoverSettlementService,
    ) {}

    public function index(): void
    {
        if (($_GET['tab'] ?? '') === 'waste') {
            $this->view('inventory/waste', $this->wasteReportService->screen());
            return;
        }
        if (($_GET['tab'] ?? '') === 'tomorrow') {
            $this->view('inventory/carryover', $this->carryoverSettlementService->screen());
            return;
        }
        $this->view('inventory/index',$this->service->snapshot());
    }
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

    public function wasteData(): JsonResponse
    {
        if (!$this->isAjaxGet()) return $this->json(['success' => false, 'reason' => 'forbidden'], 403);
        return $this->json(['success' => true, 'data' => $this->wasteReportService->screen()]);
    }

    public function waste(): JsonResponse
    {
        if (!$this->isAjaxJsonPost()) return $this->json(['success' => false, 'reason' => 'forbidden'], 403);
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) return $this->json(['success' => false, 'reason' => 'invalid_input'], 422);
        $result = $this->wasteReportService->create($input);
        $status = ($result['success'] ?? false) ? 200 : max(400, (int) ($result['code'] ?? 502));
        return $this->json($result, $status);
    }

    public function carryoverData(): JsonResponse
    {
        if (!$this->isAjaxGet()) return $this->json(['success' => false, 'reason' => 'forbidden'], 403);
        return $this->json(['success' => true, 'data' => $this->carryoverSettlementService->screen()]);
    }

    public function carryover(): JsonResponse
    {
        if (!$this->isAjaxJsonPost()) return $this->json(['success' => false, 'reason' => 'forbidden'], 403);
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) return $this->json(['success' => false, 'reason' => 'invalid_input'], 422);
        $result = $this->carryoverSettlementService->create($input);
        $status = ($result['success'] ?? false) ? 200 : max(400, (int) ($result['code'] ?? 502));
        return $this->json($result, $status);
    }

    private function isAjaxJsonPost(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
            && stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === 0
            && ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') !== 'cross-site';
    }

    private function isAjaxGet(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
            && ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') !== 'cross-site';
    }
}
