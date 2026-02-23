<?php
namespace App\Supplier\app\Services\Me;

use App\Supplier\app\Repositories\Catalog\CategoryRepository;
use App\Supplier\app\Repositories\Ingredient\IngredientRepository;
use App\Supplier\app\Repositories\Me\SupplierRepository;
use App\Supplier\core\Support\GlobalRegistry;

class SupplierService
{
    public function __construct(
        private SupplierRepository $supplierRepository
    )
    {
    }

    public function getMe()
    {
        return $this->supplierRepository->getMe(GlobalRegistry::get('user')['supplier_id']);
    }
}