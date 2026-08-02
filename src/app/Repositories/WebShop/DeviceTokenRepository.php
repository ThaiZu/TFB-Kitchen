<?php

namespace App\Kitchen\app\Repositories\WebShop;

/**
 * Le seul appel que Kitchen passe lui-même au webshop : vérifier que le jeton
 * d'appareil est toujours valide.
 *
 * Il ne passe PAS par ApiClient, et c'est volontaire : ApiClient parle à l'ERP,
 * avec sa base d'URL et le jeton de session de l'utilisateur. Le webshop est un
 * autre serveur, avec une autre identité — les mélanger enverrait tôt ou tard
 * le mauvais en-tête au mauvais hôte.
 *
 * ── Pourquoi cet appel existe ──
 * Le jeton est révocable depuis le back-office, et c'est le seul recours en cas
 * de tablette perdue. Sans cette vérification au démarrage, une tablette
 * révoquée continuerait d'afficher un cadre — vide ou non — et l'équipe
 * croirait à une panne réseau plutôt qu'à une révocation.
 *
 * ── Ce qu'il ne fait pas ──
 * Il ne rejoue pas la liste blanche des six écrans : elle est appliquée par le
 * serveur du webshop. La refaire ici donnerait un filtre contournable et deux
 * vérités à maintenir.
 */
class DeviceTokenRepository
{
    public const OK      = 'ok';
    public const REVOKED = 'revoked';
    public const NETWORK = 'network';

    /** Court : c'est un contrôle au chargement d'écran, pas un traitement. */
    private const TIMEOUT_S = 4;

    /**
     * @return string self::OK | self::REVOKED | self::NETWORK
     */
    public function check(string $apiBase, string $token): string
    {
        if ($apiBase === '' || $token === '') {
            return self::REVOKED;
        }

        $ch = curl_init(rtrim($apiBase, '/') . '/franchisee/me');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY         => false,
            CURLOPT_TIMEOUT        => self::TIMEOUT_S,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_S,
            // Le jeton voyage en en-tête, jamais en paramètre d'URL : une URL
            // se retrouve dans les journaux d'accès du serveur, dans le
            // Referer, et dans l'historique du navigateur.
            CURLOPT_HTTPHEADER     => ['X-Device-Token: ' . $token, 'Accept: application/json'],
        ]);

        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_errno($ch);
        curl_close($ch);

        // Un échec réseau n'est pas une révocation. Les confondre renverrait
        // l'équipe reconfigurer une tablette au premier hoquet de wifi.
        if ($err !== 0 || $body === false || $code === 0) {
            return self::NETWORK;
        }

        if ($code === 401 || $code === 403) {
            return self::REVOKED;
        }
        // 2xx = jeton valide. Tout le reste (500, 502, maintenance) est un
        // incident du serveur, pas un verdict sur le jeton : on ne déconfigure
        // pas la tablette pour ça.
        if ($code >= 200 && $code < 300) {
            return self::OK;
        }

        return self::NETWORK;
    }
}
