<?php

namespace App\Kitchen\app\Repositories\Client;

use App\Kitchen\core\Http\ApiClient;

class ClientRepository
{
    public function __construct(
        private ApiClient $apiClient
    ) {}

    public function getB2BByShop(int $shopId): array
    {
        $response = $this->apiClient->get("/shops/{$shopId}/clients?type=b2b");

        if (!($response['success'] ?? false)) {
            return [];
        }

        return $response['data'] ?? [];
    }

    public function getB2CByShop(int $shopId): array
    {
        $response = $this->apiClient->get("/shops/{$shopId}/clients?type=b2c");

        if (!($response['success'] ?? false)) {
            return [];
        }

        return $response['data'] ?? [];
    }

    public function insert(array $data): array
    {
        return $this->apiClient->post('/clients', $data);
    }
}
