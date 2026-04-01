<?php

namespace App\Kitchen\app\Repositories\Me;


use App\Kitchen\app\Models\Me\DeviceModel;
use App\Kitchen\core\Http\ApiClient;

class DeviceRepository
{
    public function __construct(
        private ApiClient $apiClient
    )
    {
    }

    public function getMe($id)
    {
        $resp = $this->apiClient->get('/devices/' . $id);
        return new DeviceModel($resp['data']) ?? null;
    }

}