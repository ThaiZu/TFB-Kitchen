<?php
namespace App\Kitchen\app\Services\Shop;


use App\Kitchen\app\Repositories\Shop\ShopRepository;

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