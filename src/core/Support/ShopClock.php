<?php
namespace App\Kitchen\core\Support;

use App\Kitchen\core\Http\ApiClient;

/** Request-local shop dates issued by the API, never PHP's default timezone. */
final class ShopClock
{
    private static ?ApiClient $client = null;
    private static array $dates = [];

    public static function initialize(ApiClient $client): void
    {
        self::$client = $client;
        self::$dates = [];
    }

    public static function remember(int $shop, mixed $date): bool
    {
        if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) return false;
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('UTC'));
        if (!$parsed || $parsed->format('Y-m-d') !== $date) return false;
        self::$dates[$shop] = $date;
        return true;
    }

    public static function today(int $shop): ?string
    {
        if ($shop <= 0) return null;
        if (array_key_exists($shop, self::$dates)) return self::$dates[$shop];
        self::$dates[$shop] = null;
        if (!self::$client) return null;
        try {
            $response = self::$client->get('/shops/'.$shop.'/business-clock');
            $data = $response['data'] ?? [];
            if (($response['success'] ?? false) && ($data['shop_id'] ?? null) === $shop) {
                self::remember($shop, $data['today'] ?? null);
            }
        } catch (\Throwable) {
            // Do not sign an employee session using an invented local date.
        }
        return self::$dates[$shop];
    }
}
