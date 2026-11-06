<?php
namespace App\Kitchen\app\Repositories\Auth;


use App\Kitchen\app\Models\Auth\JWTModel;
use App\Kitchen\core\Http\ApiClient;

class LoginRepository{
    private $apiClient;

    public function __construct(ApiClient $apiClient) {
        $this->apiClient = $apiClient;
    }

    public function login($data)
    {
        $response = $this->apiClient->login("/devices/auth/login", $data);

        if (isset($response['access_token']) && isset($response['refresh_token'])) {
            return $response;
        }
        return null;
    }

    public function refresh($refreshToken) {
        $response = $this->apiClient->login('/devices/auth/refresh', ['refresh_token' => $refreshToken]);
        if (is_string($response['access_token'] ?? null) && $response['access_token'] !== '' &&
            is_string($response['refresh_token'] ?? null) && $response['refresh_token'] !== '') {
            return $response;
        }
        if (in_array($response['auth_http_status'] ?? null, [400, 401, 403, 422], true)) return null;
        throw new \App\Kitchen\app\Services\Auth\SessionRefreshUnavailable('KITCHEN_SESSION_REFRESH_UNAVAILABLE');
    }
}
