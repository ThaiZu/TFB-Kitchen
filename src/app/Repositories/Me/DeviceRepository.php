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

    public function getMe()
    {
        $resp = $this->apiClient->get('/devices/me');
        if (!($resp['success'] ?? false) || !is_array($resp['data'])) {
            return null;
        }
        return new DeviceModel($resp['data']);
    }

}