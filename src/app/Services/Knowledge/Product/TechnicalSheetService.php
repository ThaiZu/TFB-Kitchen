<?php
namespace App\Kitchen\app\Services\Knowledge\Product;

use App\Kitchen\app\Repositories\Knowledge\Product\TechnicalSheetRepository;
use App\Kitchen\app\Models\Knowledge\Product\ProductDetailModel;
use App\Kitchen\core\Support\GlobalRegistry;

class TechnicalSheetService
{
    public function __construct(
        private TechnicalSheetRepository $technicalSheetRepository
    )
    {
    }

    public function getById($id): ?ProductDetailModel
    {
        return $this->technicalSheetRepository->getById($id, GlobalRegistry::get('lang_code'));
    }
}