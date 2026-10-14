<?php

namespace App\Kitchen\app\Repositories\Material;

use App\Kitchen\core\Http\ApiClient;

/** Thin Kitchen adapter for the established material stocktaking API. */
final class MaterialStocktakingRepository
{
    public function __construct(private ApiClient $api) {}

    public function materials(int $shop): array
    {
        return $this->api->get("/shops/{$shop}/material-inventory");
    }

    public function verifyCodes(int $shop, array $codes): array
    {
        return $this->api->post("/shops/{$shop}/materials/stocktakings/verify-codes", $codes);
    }

    public function create(int $shop, array $payload): array
    {
        return $this->api->post("/shops/{$shop}/materials/stocktakings", $payload);
    }
}
