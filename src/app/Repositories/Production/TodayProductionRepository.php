<?php

namespace App\Kitchen\app\Repositories\Production;

use App\Kitchen\core\Http\ApiClient;

final class TodayProductionRepository
{
    public function __construct(private ApiClient $apiClient) {}

    /** Read-only Kitchen projection of the canonical plan for the store-local today. */
    public function today(int $shopId): array
    {
        return $this->apiClient->get("/shops/{$shopId}/production/today");
    }

    /** Confirm all remaining quantities of one canonical plan cycle. */
    public function confirmCycle(int $shopId,string $cycleId,array $payload): array
    {
        return $this->apiClient->post("/shops/{$shopId}/production/today/cycles/{$cycleId}/confirm",$payload);
    }

    /** Store a revised intended quantity only; confirmation remains a separate stock write. */
    public function adjustCycleTarget(int $shopId,string $cycleId,array $payload): array
    {
        return $this->apiClient->post("/shops/{$shopId}/production/today/cycles/{$cycleId}/target",$payload);
    }
}
