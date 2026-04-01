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

    public function getAll()
    {
        return $this->productRepository->getAll(GlobalRegistry::get('lang_code'));
    }
}