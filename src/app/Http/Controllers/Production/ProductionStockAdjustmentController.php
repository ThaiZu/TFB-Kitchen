<?php

namespace App\Kitchen\app\Http\Controllers\Production;

use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\app\Services\Inventory\ProductionStockAdjustmentService;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ProductionStockAdjustmentController extends Controller
{
    public function __construct(private ProductionStockAdjustmentService $adjustments) {}

    public function index(): void
    {
        $this->view('production_stock_adjustment/index', $this->adjustments->screen());
    }

    public function add(): JsonResponse
    {
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest'
            || stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0
            || ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') {
            return $this->json(['success' => false, 'reason' => 'forbidden'], 403);
        }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) return $this->json(['success' => false, 'reason' => 'invalid_input'], 422);
        $result = $this->adjustments->add($input);
        return $this->json($result, ($result['success'] ?? false) ? 200 : max(400, (int) ($result['code'] ?? 502)));
    }
}
