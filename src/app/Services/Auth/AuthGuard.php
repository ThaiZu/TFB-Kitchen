<?php
namespace App\Kitchen\app\Services\Auth;



use App\Kitchen\app\Repositories\Auth\LoginRepository;
use App\Kitchen\core\Cookie\CookieManager;

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
        $leeway = 300; // odśwież gdy do wygaśnięcia zostało < 5 minut

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

        try {
            $response = $this->loginRepo->refresh($refresh);
        } catch (SessionRefreshUnavailable $error) {
            // A temporary API outage must not discard a usable device session.
            if (isset($exp) && $exp > $now) return true;
            throw $error;
        }

        if (!$response) {
            return false;
        }

        $newAccess  = $response['access_token'];
        $newRefresh = $response['refresh_token'];

        if ($this->jwt->getExpFromToken($newAccess) <= $now) {
            throw new SessionRefreshUnavailable('KITCHEN_SESSION_REFRESH_UNAVAILABLE');
        }
        $newExpStr = $this->jwt->getExpiryDateStringFromToken($newAccess);

        $refreshTtl = (int)($response['refresh_expires_in'] ?? CookieManager::REFRESH_TTL);
        if (!$this->cookies->setAuthCookie($newAccess, $newExpStr) ||
            !$this->cookies->setRefreshCookie($newRefresh, $refreshTtl)) {
            throw new SessionRefreshUnavailable('KITCHEN_SESSION_REFRESH_UNAVAILABLE');
        }

        return true;
    }
}
