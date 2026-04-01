<?php

namespace App\Kitchen\app\Repositories\Knowledge\Product;


use App\Kitchen\app\Models\Knowledge\Product\ProductModel;
use App\Kitchen\core\Http\ApiClient;

class ProductRepository
{
    public function __construct(
        private ApiClient $apiClient
    )
    {
    }

    public function getAll($langCode = "pl")
    {
        $queryStr = "?" . http_build_query([
                'lang_code' => $langCode
            ]);

        $response = $this->apiClient->get("/products$queryStr");

        $objects = [];

        if(isset($response['data'])){
            foreach ($response['data'] as $object){
                $objects[] = new ProductModel($object);
            }
        }

        return $objects;
    }

}