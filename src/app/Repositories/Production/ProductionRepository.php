<?php

namespace App\Kitchen\app\Repositories\Production;

use App\Kitchen\app\Models\Production\ProductionItemModel;
use App\Kitchen\core\Http\ApiClient;

/**
 * Accès au plan de production.
 *
 * Les endpoints appelés ici n'existent pas encore côté API — voir
 * docs/ENDPOINTS_PRODUCTION.md. Tant qu'ils répondent 404, les méthodes
 * renvoient null (et non un tableau vide) : l'écran doit pouvoir distinguer
 * « rien à produire aujourd'hui » de « le plan n'est pas encore servi ».
 */
class ProductionRepository
{
    public function __construct(
        private ApiClient $apiClient
    ) {}

    /**
     * Plan du jour.
     *
     * @return ProductionItemModel[]|null null si l'API ne répond pas
     */
    public function getPlan(int $shopId, string $date): ?array
    {
        $response = $this->apiClient->get("/shops/{$shopId}/production?date={$date}");

        if (!($response['success'] ?? false)) {
            return null;
        }

        $data = $response['data'] ?? [];
        $rows = $data['items'] ?? (is_array($data) ? $data : []);

        $items = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $items[] = new ProductionItemModel($row);
            }
        }
        return $items;
    }

    /**
     * Met à jour l'avancement d'une ligne.
     *
     * @param array $payload status / quantity_done / id_employee, tous facultatifs
     */
    public function updateItem(int $itemId, array $payload): array
    {
        return $this->apiClient->patch("/production/{$itemId}", $payload);
    }

    /** Compteurs par statut, pour la pastille de l'onglet. */
    public function getPendingCount(int $shopId, string $date): ?array
    {
        $response = $this->apiClient->get("/shops/{$shopId}/production/pending-count?date={$date}");
        if (!($response['success'] ?? false)) {
            return null;
        }
        return $response['data'] ?? null;
    }
}
