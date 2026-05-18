<?php
namespace App\Kitchen\app\Repositories\Complaint;
use App\Kitchen\core\Http\ApiClient;
/**
 * Minimalny dostęp do zamówień materiałowych — wyłącznie na potrzeby
 * tworzenia reklamacji w kitchen.
 */
class MaterialOrderForComplaintRepository
{
    public function __construct(
        private ApiClient $apiClient
    ) {}
    /**
     * Pobiera dostarczone zamówienia dla sklepu (do wyboru w formularzu reklamacji).
     * API: GET /shops/{shopId}/orders?status=DELIVERED&include=products
     */
    public function getDeliveredByShop(int $shopId): array
    {
        $response = $this->apiClient->where(
            "/shops/{$shopId}/orders",
            ['status' => 'DELIVERED', 'include' => 'products']
        );
        if (!($response['success'] ?? false)) {
            return [];
        }
        $orders = $response['data'] ?? [];
        // Sortuj malejąco po delivered_on (tak jak panel)
        usort($orders, static function (array $a, array $b) {
            return strtotime((string)($b['delivered_on'] ?? '')) - strtotime((string)($a['delivered_on'] ?? ''));
        });
        return $orders;
    }
}
