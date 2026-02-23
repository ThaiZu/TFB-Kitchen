<?php
namespace App\Supplier\app\Services\Auth;


use App\Supplier\core\Cookie\CookieManager;
use App\Supplier\app\Models\Auth\JWTModel;
use App\Supplier\app\Repositories\Auth\LoginRepository;
use App\Supplier\app\Services\Auth\JwtService;
use DateTime;

class AuthService {
    private $loginRepository;
    private $cookieManager;
    private $jwtService;

    public function __construct(
        LoginRepository $loginRepository,
        CookieManager $cookieManager,
        JwtService $jwtService) {
        $this->loginRepository = $loginRepository;
        $this->cookieManager = $cookieManager;
        $this->jwtService = $jwtService;
    }

    public function login($data) {
        $response = $this->loginRepository->login($data);
        if(is_null($response)) return false;

        return $this->setCookiesProcedure($response);
    }

    private function setCookiesProcedure(array $response): bool
    {
        $expiryTokenDate = $this->jwtService->getExpiryDateStringFromToken($response['access_token']);

        if(!$this->cookieManager->setAuthCookie($response['access_token'], $expiryTokenDate)) return false;
        if(!$this->cookieManager->setRefreshCookie($response['refresh_token'])) return false;

        return true;
    }


    public function logout() {
        $this->cookieManager->unsetCookies();
    }
}