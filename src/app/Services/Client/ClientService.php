<?php

namespace App\Kitchen\app\Services\Client;

use App\Kitchen\app\Repositories\Client\ClientRepository;
use App\Kitchen\core\Support\GlobalRegistry;

class ClientService
{
    public function __construct(
        private ClientRepository $clientRepository
    ) {}

    private function getShopId(): int
    {
        return (int)(GlobalRegistry::get('user')['shop_id'] ?? 0);
    }

    public function getB2BClients(): array
    {
        $shopId = $this->getShopId();
        if ($shopId <= 0) {
            return [];
        }

        return $this->clientRepository->getB2BByShop($shopId);
    }

    public function getB2CClients(): array
    {
        $shopId = $this->getShopId();
        if ($shopId <= 0) {
            return [];
        }

        return $this->clientRepository->getB2CByShop($shopId);
    }

    public function insert(array $data): array
    {
        return $this->clientRepository->insert($data);
    }
}
