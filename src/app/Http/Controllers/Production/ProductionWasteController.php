<?php

namespace App\Kitchen\app\Http\Controllers\Production;

use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\app\Services\Inventory\WasteReportService;
use Symfony\Component\HttpFoundation\JsonResponse;

/** Compact production-area entry point for one waste declaration at a time. */
final class ProductionWasteController extends Controller
{
    public function __construct(private WasteReportService $wasteReport) {}

    public function index(): void
    {
        $this->view('production_waste/index', $this->wasteReport->screen());
    }

    public function create(): JsonResponse
    {
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest'
            || stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0
            || ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') {
            return $this->json(['success' => false, 'reason' => 'forbidden'], 403);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            return $this->json(['success' => false, 'reason' => 'invalid_input'], 422);
        }

        $result = $this->wasteReport->create($input);
        return $this->json($result, ($result['success'] ?? false) ? 200 : max(400, (int) ($result['code'] ?? 502)));
    }
}
