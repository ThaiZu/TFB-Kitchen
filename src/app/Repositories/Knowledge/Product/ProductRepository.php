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

    public function getAvailableToSale(int $shopId, array $options = [])
    {
        $params = [];

        if (isset($options['include']) && $options['include']) {
            $params[] = "include=" . implode(",", $options['include']);
        }

        if (isset($options['period'])) {
            $params[] = "period=" . $options['period'];
        }

        if (isset($options['date'])) {
            $params[] = "date=" . $options['date'];
        }

        $queryStr = $params ? "?" . implode("&", $params) : "";

        $response = $this->apiClient->get("/shops/$shopId/products/available$queryStr");

        $objects = [];

        if(isset($response['data'])){
            foreach ($response['data'] as $object){
                $objects[] = new ProductModel($object);
            }
        }

        return $objects;
    }

}
