<?php
namespace App\Supplier\core\Cookie;

use App\Supplier\app\Models\Auth\JWTModel;

class CookieManager {

    public function setAuthCookie($accessToken, $expiryTokenDate)
    {

        $res_access = setcookie('supplier_access_token', $accessToken, [
            'expires' => strtotime($expiryTokenDate), // Poprawiony timestamp
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        if(!$res_access) return false;

        $res_expiry = setcookie('supplier_access_token_expiry', $expiryTokenDate, [
            'expires' => strtotime($expiryTokenDate), // Poprawiony timestamp
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        if(!$res_expiry) return false;

        return true;
    }

    public function setRefreshCookie($refreshToken)
    {
        $refreshTtl = 60*60*24*30; // 30 dni

        $expiresAt = time() + $refreshTtl;

        setcookie('supplier_refresh_token', $refreshToken, [
            'expires' => $expiresAt,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        // to cookie z datą jest opcjonalne — i tak możesz policzyć time()+TTL
        setcookie('supplier_refresh_token_expiry', date('Y-m-d H:i:s', $expiresAt), [
            'expires' => $expiresAt,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        return true;
    }

    public function unsetCookies()
    {
        setcookie('supplier_refresh_token', '', [
            'expires' => time() - 3600, // Poprawiony timestamp
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        setcookie('supplier_refresh_token_expiry', '', [
            'expires' => time() - 3600, // Poprawiony timestamp
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        setcookie('supplier_access_token', '', [
            'expires' => time() - 3600, // Poprawiony timestamp
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        setcookie('supplier_access_token_expiry', '', [
            'expires' => time() - 3600, // Poprawiony timestamp
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict',]);
    }


    public function getAccessToken()
    {
        return $_COOKIE['supplier_access_token'] ?? null;
    }

    public function getRefreshToken()
    {
        return $_COOKIE['supplier_refresh_token'] ?? null;
    }

    public function getAccessTokenExpiryTime()
    {
        return $_COOKIE['supplier_access_token_expiry'] ?? null;
    }

    public function getRefreshTokenExpiryTime()
    {
        return $_COOKIE['supplier_refresh_token_expiry'] ?? null;
    }

}