<?php

namespace App\Kitchen\app\Http\Controllers\Material;

use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\app\Services\Material\MaterialStocktakingService;
use Symfony\Component\HttpFoundation\JsonResponse;

final class MaterialStocktakingController extends Controller
{
    public function __construct(private MaterialStocktakingService $stocktaking) {}

    public function index(): void { $this->view('material_stocktaking/index', $this->stocktaking->screen()); }
    public function preview(): JsonResponse { return $this->write(fn (array $input) => $this->stocktaking->previewScanner($input)); }
    public function save(): JsonResponse { return $this->write(fn (array $input) => $this->stocktaking->save($input)); }

    private function write(callable $operation): JsonResponse
    {
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest'
            || stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0
            || ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') {
            return $this->json(['success' => false, 'reason' => 'forbidden'], 403);
        }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) return $this->json(['success' => false, 'reason' => 'invalid_input'], 422);
        $result = $operation($input);
        return $this->json($result, ($result['success'] ?? false) ? 200 : max(400, (int) ($result['code'] ?? 502)));
    }
}
