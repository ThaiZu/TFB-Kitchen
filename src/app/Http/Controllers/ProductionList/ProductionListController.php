<?php

namespace App\Kitchen\app\Http\Controllers\ProductionList;

use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\app\Services\ProductionList\ProductionListService;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ProductionListController extends Controller
{
    public function __construct(private ProductionListService $productionListService)
    {
    }

    public function index(): void
    {
        $date = $this->productionListService->readDate($_GET['date'] ?? null);
        $margin = $this->productionListService->readMargin($_GET['margin'] ?? null);

        $this->view('production_list/index', $this->productionListService->build($date, $margin));
    }

    public function receipt(): void
    {
        $date=$this->productionListService->readDate($_GET['date'] ?? null);
        $this->view('production_list/receive',$this->productionListService->saved($date));
    }

    public function save(): JsonResponse { return $this->write('save'); }
    public function pdf(): void
    {
        $date=$this->productionListService->readDate($_GET['date'] ?? null);
        $result=$this->productionListService->pdf($date);
        if (!($result['success'] ?? false) || !is_string($result['data'] ?? null) || !str_starts_with($result['data'],'%PDF-')) {
            http_response_code(502);
            $this->view('production_list/saved',$this->productionListService->saved($date)+['pdf_error'=>true]);
            return;
        }
        (new \Symfony\Component\HttpFoundation\Response($result['data'],200,[
            'Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="production-plan-'.$date.'.pdf"','Cache-Control'=>'private, no-store'
        ]))->send();
    }
    public function receive(): JsonResponse { return $this->write('receive'); }

    private function write(string $action): JsonResponse
    {
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest'
            || stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0
            || ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') {
            return $this->json(['success' => false, 'reason' => 'forbidden'], 403);
        }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) return $this->json(['success' => false, 'reason' => 'invalid_input'], 422);
        $result = $this->productionListService->{$action}($input);
        $status = ($result['success'] ?? false) ? 200 : (int) ($result['code'] ?? 502);
        if ($status < 400 && !($result['success'] ?? false)) $status = 502;
        return $this->json(['success' => (bool) ($result['success'] ?? false), 'reason' => $result['reason'] ?? null], $status);
    }
}
