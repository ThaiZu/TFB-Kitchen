<?php

namespace App\Kitchen\app\Repositories\Me;


use App\Kitchen\app\Models\Me\KitchenModel;
use App\Kitchen\core\Http\ApiClient;

class KitchenRepository
{
    public function __construct(
        private ApiClient $apiClient
    )
    {
    }

    public function getMe($id)
    {
        $resp = $this->apiClient->get('/material-suppliers/' . $id);
        return new KitchenModel($resp['data']) ?? null;
    }

}