<?php
namespace App\Kitchen\app\Repositories\ProductionList;
use App\Kitchen\core\Http\ApiClient;
final class ProductionListRepository
{
    public function __construct(private ApiClient $apiClient) {}
    public function plan(int $shopId, string $date, float $margin): array
    { return $this->apiClient->where("/shops/{$shopId}/production-plans", compact('date', 'margin')); }
    public function save(int $shopId, array $payload): array
    { return $this->apiClient->put("/shops/{$shopId}/production-plans", $payload); }
    public function saved(int $shopId, string $date): array
    { return $this->apiClient->where("/shops/{$shopId}/production-plans/saved",compact('date')); }
    public function pdf(int $shopId, string $date): array
    { return $this->apiClient->where("/shops/{$shopId}/production-plans/pdf",compact('date'),false); }
    public function receive(int $shopId, array $payload): array
    { return $this->apiClient->post("/shops/{$shopId}/production-plans/receipts", $payload); }
}
