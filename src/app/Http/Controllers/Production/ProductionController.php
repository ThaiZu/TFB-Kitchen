<?php

namespace App\Kitchen\app\Http\Controllers\Production;

use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\app\Services\Production\ProductionService;

class ProductionController extends Controller
{
    public function __construct(
        private ProductionService $productionService
    ) {}

    /**
     * GET /production[?date=Y-m-d]
     */
    public function index(): void
    {
        $date = $_GET['date'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        // null = l'API ne sert pas encore le plan ; [] = rien à produire.
        // La vue doit dire deux choses différentes.
        $items = $this->productionService->getPlan($date);

        $this->view('production/index', [
            'plan_available' => $items !== null,
            'items'          => $items ?? [],
            'groups'         => $this->productionService->groupByStatus($items ?? []),
            'summary'        => $this->productionService->summarise($items ?? []),
            'selected_date'  => $date,
            'today'          => date('Y-m-d'),
        ]);
    }

    /**
     * PATCH /ajax/production/{id}
     *
     * Avancement d'une ligne : statut et/ou quantité produite.
     */
    public function ajaxUpdate(int $id): void
    {
        $input = json_decode(file_get_contents('php://input') ?: '', true);

        if (!is_array($input)) {
            $json = $this->json(['status' => 'error', 'description' => 'invalid_payload'], 400);
            $json->send();
            return;
        }

        $payload = [];
        if (isset($input['status'])) {
            $payload['status'] = (string)$input['status'];
        }
        if (isset($input['quantity_done'])) {
            $payload['quantity_done'] = max(0, (float)$input['quantity_done']);
        }
        if (isset($input['id_employee'])) {
            $payload['id_employee'] = (int)$input['id_employee'];
        }

        if ($payload === []) {
            $json = $this->json(['status' => 'error', 'description' => 'nothing_to_update'], 400);
            $json->send();
            return;
        }

        $response = $this->productionService->updateItem($id, $payload);
        $status   = ($response['success'] ?? false) ? 200 : 502;

        $json = $this->json($response, $status);
        $json->send();
    }
}
