<?php
namespace App\Kitchen\app\Services\Knowledge\Product;

use App\Kitchen\app\Repositories\Knowledge\Product\ProductRepository;
use App\Kitchen\app\Repositories\Me\DeviceRepository;
use App\Kitchen\core\Support\GlobalRegistry;

class ProductService
{
    public function __construct(
        private ProductRepository $productRepository
    )
    {
    }

    public function getAvailableToSale(array $options = [])
    {
        $shopId = (int)(GlobalRegistry::get('user')['shop_id'] ?? 0);
        return $this->productRepository->getAvailableToSale($shopId, $options);
    }
}
