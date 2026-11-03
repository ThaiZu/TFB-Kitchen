<?php

namespace App\Kitchen\core\Support;

use App\Kitchen\app\Services\Me\DeviceModeService;

/** Legacy device settings storage. Kitchen always uses local Production navigation. */
final class DeviceMode
{
    public const COOKIE_MODE   = 'kitchen_device_mode';
    public const COOKIE_URL    = 'kitchen_webshop_url';
    public const COOKIE_TOKEN  = 'kitchen_webshop_token';
    public const COOKIE_CONFIG = 'kitchen_mode_config';

    /** Cinq ans : le réglage doit survivre au redémarrage, pas expirer tout seul. */
    private const TTL = 5 * 365 * 24 * 3600;

    /**
     * Dix minutes pour la configuration des modes.
     *
     * Elle change rarement — quelqu'un coche une case au back-office — mais
     * quand elle change, l'équipe ne doit pas attendre le lendemain. Dix minutes
     * est le compromis : un appel par tablette et par tranche de dix minutes au
     * lieu d'un appel à chaque page, et un réglage qui descend dans le quart
     * d'heure.
     */
    private const TTL_CONFIG = 600;

    private static ?DeviceModeService $rules = null;

    public static function rules(): DeviceModeService
    {
        if (self::$rules === null) {
            self::$rules = new DeviceModeService();
        }

        return self::$rules;
    }

    /** Compatibility hook: the retired device configuration is never fetched. */
    public static function refreshConfig(callable $fetch): void
    {
        // Production is configured locally; do not call the retired config endpoint.
    }

    /**
     * La configuration en cache, ou null si elle est absente ou périmée.
     *
     * Le cookie n'est pas signé, et ce n'est pas un oubli : il ne porte que des
     * clés de menu, toutes filtrées par DeviceModeService::sanitise() contre une
     * liste fermée. Le forger ne fait qu'afficher ou masquer une entrée que
     * l'utilisateur peut de toute façon atteindre en tapant l'URL — le mode
     * lui-même, changeable dans les réglages, en fait déjà autant. Aucune
     * décision d'autorisation n'en dépend.
     */
    private static function cachedConfig(): ?array
    {
        $raw = (string)($_COOKIE[self::COOKIE_CONFIG] ?? '');
        if ($raw === '') {
            return null;
        }

        $d = json_decode($raw, true);
        if (!is_array($d) || !isset($d['t'], $d['c']) || !is_array($d['c'])) {
            return null;
        }
        $t = (int)$d['t'];
        // Daté du futur : horloge touchée, on ne lui fait pas crédit.
        if ($t > time() || time() - $t > self::TTL_CONFIG) {
            return null;
        }

        return $d['c'];
    }

    public static function current(): string
    {
        return self::rules()->normalise($_COOKIE[self::COOKIE_MODE] ?? null);
    }

    /**
     * Écrit le réglage et le rend lisible dans la même requête : sans la mise
     * à jour de $_COOKIE, la page rendue juste après l'enregistrement
     * afficherait encore l'ancien mode, et le réglage aurait l'air de n'avoir
     * pas pris.
     */
    public static function remember(string $mode): string
    {
        $mode = self::rules()->normalise($mode);
        self::write(self::COOKIE_MODE, $mode);

        return $mode;
    }

    public static function rememberWebshopUrl(?string $url): void
    {
        $url = trim((string)$url);
        // On ne stocke pas une URL qu'on refuserait d'ouvrir : le contrôle est
        // au plus près de l'écriture, pas seulement à l'affichage.
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            $url = '';
        }
        self::write(self::COOKIE_URL, $url);
    }

    /**
     * Le jeton d'appareil. C'est un secret : le cookie est HttpOnly, donc
     * illisible par le JavaScript de la page, et il ne part jamais vers une
     * autre origine que Kitchen. Il n'est écrit dans aucun log, ne passe par
     * aucune URL, et n'est jamais rendu dans le HTML — voir
     * DeviceModeService::tokenHint().
     */
    public static function rememberWebshopToken(?string $token): void
    {
        self::write(self::COOKIE_TOKEN, trim((string)$token));
    }

    /**
     * L'URL de base du back-office : réglage de l'appareil s'il existe, sinon
     * la valeur du serveur. L'ordre importe — c'est la config serveur qui
     * évite de saisir la même URL sur cinquante tablettes, et le réglage local
     * qui permet d'en dévier une pour un test.
     */
    public static function webshopBase(): string
    {
        $local = trim((string)($_COOKIE[self::COOKIE_URL] ?? ''));
        if ($local !== '') {
            return $local;
        }

        return defined('WEBSHOP_BO_URL') ? trim((string)WEBSHOP_BO_URL) : '';
    }

    /** true si l'URL vient du serveur et non de cette tablette. */
    public static function webshopBaseIsGlobal(): bool
    {
        return trim((string)($_COOKIE[self::COOKIE_URL] ?? '')) === '';
    }

    /**
     * Le jeton d'appareil de CETTE tablette.
     *
     * Pas de valeur serveur en repli, contrairement à l'URL : un jeton vaut
     * pour une boutique, et un même serveur Kitchen sert plusieurs magasins.
     * Une valeur globale ouvrirait donc le back-office du mauvais magasin —
     * ou plutôt le même pour tout le monde, ce qui est pire.
     */
    public static function webshopToken(): string
    {
        return trim((string)($_COOKIE[self::COOKIE_TOKEN] ?? ''));
    }

    private static function write(string $name, string $value, ?int $ttl = null): void
    {
        setcookie($name, $value, [
            'expires'  => $value === '' ? time() - 3600 : time() + ($ttl ?? self::TTL),
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        if ($value === '') {
            unset($_COOKIE[$name]);
        } else {
            $_COOKIE[$name] = $value;
        }
    }
}
