<?php
namespace App\Kitchen\app\Services\Me;

use App\Kitchen\app\Repositories\Me\DeviceRepository;
use App\Kitchen\core\Support\GlobalRegistry;

class ShopService
{
    public function __construct(
        private ShopRepository $shopRepository
    )
    {
    }

    public function getAll()
    {
        return $this->shopRepository->getAll();
    }
}