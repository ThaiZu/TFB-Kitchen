<?php

namespace App\Supplier\app\Repositories\Me;


use App\Supplier\app\Models\Me\SupplierModel;
use App\Supplier\core\Http\ApiClient;

class SupplierRepository
{
    public function __construct(
        private ApiClient $apiClient
    )
    {
    }

    public function getMe($id)
    {
        $resp = $this->apiClient->get('/material-suppliers/' . $id);
        return new SupplierModel($resp['data']) ?? null;
    }

}