<?php

namespace App\Kitchen\app\Repositories\Shop;


use App\Kitchen\app\Models\Me\ShopModel;
use App\Kitchen\core\Http\ApiClient;

class ShopRepository
{
    public function __construct(
        private ApiClient $apiClient
    )
    {
    }

    public function getAll()
    {
        $response = $this->apiClient->get('/public/shops');

        $objects = [];

        if(isset($response['data'])){
            foreach ($response['data'] as $object){
                $objects[] = new ShopModel($object);
            }
        }

        return $objects;
    }

}