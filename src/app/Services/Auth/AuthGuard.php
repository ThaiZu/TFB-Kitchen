<?php
namespace App\Supplier\app\Services\Auth;



use App\Supplier\app\Repositories\Auth\LoginRepository;
use App\Supplier\core\Cookie\CookieManager;

class AuthGuard
{
    public function __construct(
        private CookieManager $cookies,
        private JwtService $jwt,
        private LoginRepository $loginRepo
    ) {}

    public function ensureAccessToken(): bool
    {
        $access  = $this->cookies->getAccessToken();
        $refresh = $this->cookies->getRefreshToken();

        $now = time();
        $leeway = 10;

        // 1) Jeśli mamy access, sprawdź exp
        if ($access) {
            $exp = $this->jwt->getExpFromToken($access);

            // access ważny
            if ($exp > 0 && ($exp - $now) > $leeway) {
                return true;
            }
            // access jest, ale już niepewny -> lecimy do refresh
        }

        // 2) Brak access albo wygasł -> spróbuj refresh
        if (!$refresh) {
            return false;
        }

        $response = $this->loginRepo->refresh($refresh); // musi zwracać ?array

        if (!$response) {
            return false;
        }

        $newAccess  = $response['access_token'];
        $newRefresh = $response['refresh_token'];

        $newExpStr = $this->jwt->getExpiryDateStringFromToken($newAccess);

        $this->cookies->setAuthCookie($newAccess, $newExpStr);
        $this->cookies->setRefreshCookie($newRefresh);

        return true;
    }
}
