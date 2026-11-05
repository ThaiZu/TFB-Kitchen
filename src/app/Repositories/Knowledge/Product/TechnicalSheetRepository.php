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

        // Same source as catalogue thumbnails; do not replace technical recipe photos.
        try{
            $image=$this->apiClient->get('/products/'.(int)$id.'/webshop-image');
            if(($image['success']??false)===true)$data['object']['webshop_image']=$image['data']['image']??null;
        }catch(\Throwable $error){error_log('Kitchen product image unavailable: '.get_class($error).' ('.$error->getCode().')');}

        return new ProductDetailModel($data);
    }

}