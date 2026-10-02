<?php

namespace App\Kitchen\app\Repositories\Inventory;

use App\Kitchen\core\Http\ApiClient;

final class CarryoverSettlementRepository
{
    public function __construct(private ApiClient $apiClient) {}

    public function sheet(int $shopId): array
    {
        return $this->apiClient->get("/shops/{$shopId}/carryover-settlement");
    }

    public function create(int $shopId, array $payload): array
    {
        return $this->apiClient->post("/shops/{$shopId}/carryover-settlement", $payload);
    }
}
