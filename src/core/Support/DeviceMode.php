<?php

namespace App\Kitchen\core\Support;

use App\Kitchen\app\Services\Me\DeviceModeService;

/**
 * Persistance du mode de la tablette.
 *
 * Les règles vivent dans DeviceModeService (pures, testées) ; ici on ne fait
 * que les brancher sur le stockage. La séparation compte : c'est ce qui permet
 * de vérifier les règles sans navigateur, et de remplacer le stockage sans
 * toucher aux règles.
 *
 * ── Pourquoi un cookie et pas l'ERP ──
 * Le brief demande, par ordre de préférence, de porter le mode sur l'appareil
 * côté ERP (`GET /devices/me`). Cet endpoint existe et est déjà consommé
 * (DeviceRepository), mais sa réponse ne porte aucun champ `mode` et Kitchen
 * n'a pas d'écriture sur l'appareil : on prend donc explicitement l'option 2
 * du brief — un cookie persistant côté Kitchen — et on documente le champ à
 * ajouter côté ERP dans docs/MODE_TABLETTE.md. Dès que l'API le portera,
 * seules les deux méthodes de lecture ci-dessous changeront.
 *
 * Le cookie est un réglage d'appareil, pas une identité : il n'entre dans
 * aucune décision d'autorisation. Le forger ne donne accès à rien — chaque
 * module reste protégé par son jeton, et le back-office WebShop par sa propre
 * session PIN. Il ne fait que choisir des entrées de menu.
 */
final class DeviceMode
{
    public const COOKIE_MODE = 'kitchen_device_mode';
    public const COOKIE_URL  = 'kitchen_webshop_url';
    public const COOKIE_SHOP = 'kitchen_webshop_shop';

    /** Cinq ans : le réglage doit survivre au redémarrage, pas expirer tout seul. */
    private const TTL = 5 * 365 * 24 * 3600;

    private static ?DeviceModeService $rules = null;

    public static function rules(): DeviceModeService
    {
        return self::$rules ??= new DeviceModeService();
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

    public static function rememberWebshop(?string $url, $shopId): void
    {
        $url = trim((string)$url);
        // On ne stocke pas une URL qu'on refuserait d'ouvrir : le contrôle est
        // au plus près de l'écriture, pas seulement à l'affichage.
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            $url = '';
        }
        self::write(self::COOKIE_URL, $url);
        self::write(self::COOKIE_SHOP, (int)$shopId > 0 ? (string)(int)$shopId : '');
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
     * La boutique du back-office : celle de la session de connexion, sauf
     * réglage explicite sur l'appareil. La session fait foi par défaut parce
     * qu'elle est déjà ce qui détermine tout le reste de l'écran ; un id saisi
     * à la main qui la contredirait afficherait un back-office et des données
     * Kitchen appartenant à deux magasins différents.
     */
    public static function webshopShopId(): int
    {
        $local = (int)($_COOKIE[self::COOKIE_SHOP] ?? 0);
        if ($local > 0) {
            return $local;
        }

        return (int)(GlobalRegistry::get('user')['shop_id'] ?? 0);
    }

    public static function webshopShopIsSession(): bool
    {
        return (int)($_COOKIE[self::COOKIE_SHOP] ?? 0) <= 0;
    }

    private static function write(string $name, string $value): void
    {
        setcookie($name, $value, [
            'expires'  => $value === '' ? time() - 3600 : time() + self::TTL,
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
