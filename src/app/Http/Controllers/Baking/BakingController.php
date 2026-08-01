<?php

namespace App\Kitchen\app\Http\Controllers\Baking;

use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\app\Models\Baking\BakingBatchModel;
use App\Kitchen\app\Services\Baking\BakingService;

class BakingController extends Controller
{
    public function __construct(
        private BakingService $bakingService
    ) {}

    /**
     * GET /baking[?stage=prep|cook|finish]
     *
     * Toujours pour aujourd'hui : pas de sélecteur de date, une cuisine ne
     * cuit pas pour hier.
     */
    public function index(): void
    {
        $stage = $this->readStage();
        $plan  = $this->bakingService->getPlan();

        if ($plan === null) {
            $this->view('baking/index', [
                'plan_available' => false,
                'active_stage'   => $stage,
                'batches'        => [],
                'counts'         => ['all' => 0, 'prep' => 0, 'cook' => 0, 'finish' => 0],
                'now'            => $this->bakingService->nowMinutes(),
                'now_clock'      => date('H:i'),
                'window'         => ['from' => 0, 'to' => 60, 'hours' => []],
            ]);
            return;
        }

        $now     = $this->bakingService->nowMinutes($plan['server_time']);
        $active  = $this->bakingService->active($plan['batches']);
        $shown   = $this->bakingService->filterByStage($active, $stage);

        $this->view('baking/index', [
            'plan_available' => true,
            'active_stage'   => $stage,
            'batches'        => $shown,
            'counts'         => $this->bakingService->countByStage($active),
            'now'            => $now,
            'now_clock'      => BakingBatchModel::toClock($now),
            // La frise se cadre sur ce qui est affiché : filtrer resserre aussi
            // la fenêtre, au lieu de laisser deux fournées perdues dans la
            // largeur de la matinée.
            'window'         => $this->bakingService->window($shown ?: $active, $now),
        ]);
    }

    /**
     * GET /ajax/baking
     *
     * Même contenu que l'écran, en JSON : le plan bouge en continu, et une
     * fournée sortie par un collègue doit disparaître de la tablette d'à côté
     * sans que personne ne rafraîchisse.
     */
    public function ajaxPlan(): void
    {
        $stage = $this->readStage();
        $plan  = $this->bakingService->getPlan();

        if ($plan === null) {
            $this->json(['success' => false, 'plan_available' => false], 502)->send();
            return;
        }

        $now    = $this->bakingService->nowMinutes($plan['server_time']);
        $active = $this->bakingService->active($plan['batches']);
        $shown  = $this->bakingService->filterByStage($active, $stage);

        $this->json([
            'success'        => true,
            'plan_available' => true,
            'now'            => $now,
            'now_clock'      => BakingBatchModel::toClock($now),
            'counts'         => $this->bakingService->countByStage($active),
            'window'         => $this->bakingService->window($shown ?: $active, $now),
            'batches'        => array_map(fn(BakingBatchModel $b) => $this->expose($b, $now), $shown),
        ])->send();
    }

    /**
     * PATCH /ajax/baking/{id}
     *
     * Corps : { status } — ou rien, et l'étape suivante est déduite.
     */
    public function ajaxAdvance(int $id): void
    {
        $input = json_decode(file_get_contents('php://input') ?: '', true);
        $input = is_array($input) ? $input : [];

        $status = isset($input['status']) ? strtoupper((string)$input['status']) : null;
        if ($status === null || $status === '') {
            $this->json(['success' => false, 'description' => 'status_required'], 400)->send();
            return;
        }

        $response = $this->bakingService->advance(
            $id,
            $status,
            isset($input['id_employee']) ? (int)$input['id_employee'] : null
        );

        $this->json($response, ($response['success'] ?? false) ? 200 : 502)->send();
    }

    /**
     * Forme d'une fournée pour le rafraîchissement : tout ce que la vue
     * calcule est calculé ici aussi, pour que le JavaScript n'ait pas à
     * refaire l'arithmétique des étapes de son côté.
     */
    private function expose(BakingBatchModel $b, int $now): array
    {
        return $b->jsonSerialize() + [
            'stage_end'      => $b->getStageEndClock(),
            'progress'       => $b->getProgressPercent($now),
            'next_status'    => $b->getNextStatus(),
            'is_waiting'     => $b->isWaiting(),
            'is_ready_bake'  => $b->isReadyToBake(),
            'prep_start_min' => $b->getPrepStart(),
            'prep_end_min'   => $b->getPrepEnd(),
            'cook_start_min' => $b->getCookStart(),
            'cook_end_min'   => $b->getCookEnd(),
            'finish_end_min' => $b->getFinishEnd(),
            'shelf_min'      => $b->getShelfTime(),
        ];
    }

    private function readStage(): string
    {
        $stage = (string)($_GET['stage'] ?? '');
        return in_array($stage, BakingService::STAGES, true) ? $stage : 'all';
    }
}
