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

        $data['baking_available'] = false;
        $shop=(int)(\App\Kitchen\core\Support\GlobalRegistry::get('user')['shop_id'] ?? 0);
        if ($shop>0) {
            try {
                $baking=$this->apiClient->get('/shops/'.$shop.'/products/'.(int)$id.'/baking-parameters');
                $payload=$baking['data'] ?? null;
                if (($baking['success']??false)===true && is_array($payload)
                    && (int)($payload['shop_id']??0)===$shop && (int)($payload['product_id']??0)===(int)$id
                    && is_array($payload['stages']??null) && is_array($payload['local']??null)) {
                    $data['baking']=$payload;
                    $data['baking_available']=true;
                }
            } catch (\Throwable $error) { error_log('Kitchen baking parameters unavailable'); }
        }

        return new ProductDetailModel($data);
    }

}