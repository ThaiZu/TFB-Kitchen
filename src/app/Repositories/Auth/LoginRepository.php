<?php
namespace App\Supplier\app\Repositories\Auth;


use App\Supplier\app\Models\Auth\JWTModel;
use App\Supplier\core\Http\ApiClient;

class LoginRepository{
    private $apiClient;

    public function __construct(ApiClient $apiClient) {
        $this->apiClient = $apiClient;
    }

    public function login($data)
    {
        $response = $this->apiClient->login("/material-suppliers/auth/login", $data);

        if (isset($response['access_token']) && isset($response['refresh_token'])) {
            return $response;
        }
        return null;
    }

    public function refresh($refreshToken) {
        $response = $this->apiClient->login('/material-suppliers/auth/refresh', ['refresh_token' => $refreshToken]);

        if (isset($response['access_token']) && isset($response['refresh_token'])) {
            return $response;
        }
        return null;
    }
}