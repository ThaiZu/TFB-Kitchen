<?php
namespace App\Supplier\app\Http\Middleware;

use App\Supplier\app\Services\Auth\AuthGuard;
use App\Supplier\app\Services\Auth\AuthService;
use App\Supplier\app\Services\Auth\JwtService;
use App\Supplier\core\Cookie\CookieManager;
use App\Supplier\core\Support\GlobalRegistry;
use DateTime;

class AuthMiddleware
{

    public function __construct(
        private AuthGuard $authGuard,
        private CookieManager  $cookieManager,
        private JwtService     $jwtService,
    )
    {
    }

    public function handle()
    {
        if (!$this->authGuard->ensureAccessToken()) {
            $this->cookieManager->unsetCookies();
            Redirect("/auth");
            exit;
        }

        // --- TU budujesz kontekst ---
        $access = $this->cookieManager->getAccessToken();

        if ($access) {
            $claims = $this->jwtService->getClaimsUnsafe($access); // albo jwtService->getClaimsUnsafe

            GlobalRegistry::set('user', [
                'id' => (int)($claims['usr_id'] ?? 0),
                'supplier_id' => (int)($claims['supplier_id'] ?? 0),
                'first_name' => (string)($claims['usr_fn'] ?? ''),
                'last_name' => (string)($claims['usr_ln'] ?? ''),
                'lang_code' => (string)($claims['usr_lncd'] ?? 'pl')
            ]);
            GlobalRegistry::set('lang_code', (string)($claims['usr_lncd'] ?? getUserLanguage()));
        }
    }
}