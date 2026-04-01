<?php

namespace App\Kitchen\app\Repositories\Knowledge\Product;


use App\Kitchen\app\Models\Knowledge\Product\ProductDetailModel;
use App\Kitchen\core\Http\ApiClient;

class TechnicalSheetRepository
{
    public function __construct(
        private ApiClient $apiClient
    )
    {
    }

    public function getById($id, $langCode = "pl"): ?ProductDetailModel
    {
        $queryStr = "?" . http_build_query([
                'lang_code' => $langCode
            ]);

        $response = $this->apiClient->get("/products/$id/technical-sheet/raw$queryStr");

        if (!isset($response['success']) || !$response['success']) {
            return null;
        }

        $data = $response['data'] ?? null;

        if (!$data) {
            return null;
        }

        return new ProductDetailModel($data);
    }

}