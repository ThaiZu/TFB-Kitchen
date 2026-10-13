<?php

namespace App\Kitchen\app\Http\Controllers\Production;

use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\app\Services\Production\TodayProductionService;
use Symfony\Component\HttpFoundation\JsonResponse;

final class TodayProductionController extends Controller
{
    public function __construct(private TodayProductionService $production) {}

    public function index(): void
    {
        $this->view('production_today/index', $this->production->view());
    }

    public function confirmCycle(): JsonResponse
    {
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest'
            || stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0
            || ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') return $this->json(['success'=>false,'reason'=>'forbidden'],403);
        $input=json_decode(file_get_contents('php://input'),true);
        if (!is_array($input)) return $this->json(['success'=>false,'reason'=>'invalid_input'],422);
        $result=$this->production->confirmCycle($input);
        return $this->json($result,($result['success'] ?? false) ? 200 : max(400,(int)($result['code'] ?? 502)));
    }

    public function adjustCycleTarget(): JsonResponse
    {
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest'
            || stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0
            || ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') return $this->json(['success'=>false,'reason'=>'forbidden'],403);
        $input=json_decode(file_get_contents('php://input'),true);
        if (!is_array($input)) return $this->json(['success'=>false,'reason'=>'invalid_input'],422);
        $result=$this->production->adjustCycleTarget($input);
        return $this->json($result,($result['success'] ?? false) ? 200 : max(400,(int)($result['code'] ?? 502)));
    }
}
