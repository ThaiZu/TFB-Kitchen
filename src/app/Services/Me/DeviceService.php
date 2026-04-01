<?php
namespace App\Kitchen\app\Services\Me;

use App\Kitchen\app\Repositories\Me\KitchenRepository;
use App\Kitchen\core\Support\GlobalRegistry;

class KitchenService
{
    public function __construct(
        private KitchenRepository $supplierRepository
    )
    {
    }

    public function getMe()
    {
        return $this->supplierRepository->getMe(GlobalRegistry::get('user')['supplier_id']);
    }
}