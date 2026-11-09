<?php
namespace App\Kitchen\app\Http\Middleware;

use App\Kitchen\app\Services\Auth\AuthGuard;
use App\Kitchen\app\Services\Auth\SessionRefreshUnavailable;
use App\Kitchen\app\Services\Auth\SessionResponse;
use App\Kitchen\app\Services\Auth\AuthService;
use App\Kitchen\app\Services\Auth\JwtService;
use App\Kitchen\core\Cookie\CookieManager;
use App\Kitchen\core\Support\GlobalRegistry;
use DateTime;

class AuthMiddleware
{

    public function __construct(
        private AuthGuard $authGuard,
        private CookieManager  $cookieManager,
        private JwtService     $jwtService,
        private \App\Kitchen\core\Http\ApiClient $apiClient,
    )
    {
    }

    public function handle()
    {
        try {
            $authenticated = $this->authGuard->ensureAccessToken();
        } catch (SessionRefreshUnavailable) {
            SessionResponse::create($_SERVER, true, ROOT)->send();
            exit;
        }
        if (!$authenticated) {
            $this->cookieManager->unsetCookies();
            SessionResponse::create($_SERVER, false, ROOT)->send();
            exit;
        }

        \App\Kitchen\core\Support\ShopClock::initialize($this->apiClient);

        // --- TU budujesz kontekst ---
        $access = $this->cookieManager->getAccessToken();

        if ($access) {
            $claims = $this->jwtService->getClaimsUnsafe($access); // albo jwtService->getClaimsUnsafe

            GlobalRegistry::set('user', [
                'shop_id' => (int)($claims['shop_id'] ?? 0),
                'device_id' => (int)($claims['device_id'] ?? 0),
                'device_name' => (string)($claims['dv_nm'] ?? ''),
                'lang_code' => (string)($claims['dv_lncd'] ?? 'pl')
            ]);
            GlobalRegistry::set('lang_code', (string)($claims['dv_lncd'] ?? getUserLanguage()));
        }
    }
}