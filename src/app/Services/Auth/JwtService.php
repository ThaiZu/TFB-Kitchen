<?php
namespace App\Supplier\app\Services\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService {

    public function getExpFromToken(string $jwt): int
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) return 0;

        $payload = json_decode($this->base64UrlDecode($parts[1]), true);
        return (int)($payload['exp'] ?? 0);
    }

    public function getExpiryDateStringFromToken(string $jwt): string
    {
        $exp = $this->getExpFromToken($jwt);
        if ($exp <= 0) return date('Y-m-d H:i:s');
        return date('Y-m-d H:i:s', $exp);
    }

    public function getClaimsUnsafe(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) return [];
        return json_decode($this->base64UrlDecode($parts[1]), true) ?: [];
    }

    private function base64UrlDecode(string $data): string
    {
        $data = str_replace(['-', '_'], ['+', '/'], $data);
        $padLen = 4 - (strlen($data) % 4);
        if ($padLen < 4) $data .= str_repeat('=', $padLen);
        return base64_decode($data) ?: '';
    }
}