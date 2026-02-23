<?php
namespace App\Supplier\app\Repositories\Helpers;


use App\Supplier\app\Models\Catalog\AllergenModel;
use App\Supplier\app\Models\Catalog\CategoryModel;
use App\Supplier\core\Http\ApiClient;

class AllergenRepository
{
    public function __construct(
        private ApiClient $apiClient
    )
    {
    }

    public function getAll()
    {

        $response = $this->apiClient->get("/allergens");

        $objects = [];
        if(isset($response['data'])){
            foreach ($response['data'] as $object){
                $objects[] = new AllergenModel($object);
            }
        }

        return $objects;
    }

}