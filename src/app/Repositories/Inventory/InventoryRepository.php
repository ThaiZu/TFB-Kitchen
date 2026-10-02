<?php
namespace App\Kitchen\app\Repositories\Inventory;
use App\Kitchen\core\Http\ApiClient;
final class InventoryRepository
{
    public function __construct(private ApiClient $apiClient) {}
    public function snapshot(int $shop): array
    { return $this->apiClient->get("/shops/{$shop}/inventory-count"); }
    public function count(int $shop, int $product, array $payload): array
    { return $this->apiClient->post("/shops/{$shop}/inventory-count/{$product}", $payload); }
}
