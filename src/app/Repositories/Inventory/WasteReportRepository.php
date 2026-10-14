<?php

namespace App\Kitchen\app\Repositories\Inventory;

use App\Kitchen\core\Http\ApiClient;

final class WasteReportRepository
{
    public function __construct(private ApiClient $apiClient) {}

    public function reasons(int $shopId): array
    {
        return $this->apiClient->get("/shops/{$shopId}/waste-reasons");
    }

    public function today(int $shopId): array
    {
        return $this->apiClient->get("/shops/{$shopId}/waste-reports/today");
    }

    public function create(int $shopId, array $payload): array
    {
        return $this->apiClient->post("/shops/{$shopId}/waste-reports", $payload);
    }
}
