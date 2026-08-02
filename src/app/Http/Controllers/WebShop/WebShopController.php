<?php

namespace App\Kitchen\app\Http\Controllers\WebShop;

use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\app\Repositories\WebShop\DeviceTokenRepository;
use App\Kitchen\core\Support\DeviceMode;
use App\Kitchen\core\Support\Route;

/**
 * Mode WebShop : on ouvre le back-office franchisé, on ne le réécrit pas.
 *
 * Le back-office existe, il est déployé, et compte 37 sections en 6 groupes.
 * Le refaire ici donnerait deux implémentations à maintenir, donc deux
 * comportements qui divergeraient — et il gère déjà tout ce qui est délicat :
 * portée du jeton, cloisonnement à la boutique, révocation, liste blanche des
 * écrans. Kitchen lui donne un cadre, rien de plus.
 *
 * ── Aucune connexion personnelle ──
 * La tablette est posée dans le magasin et sert à toute l'équipe. L'identité,
 * c'est l'appareil : une URL et un jeton, configurés une fois. Le jeton
 * n'apparaît ni dans l'URL du cadre, ni dans la page, ni dans un log — il ne
 * sert qu'à l'appel de vérification, en en-tête.
 *
 * Le jeton admin de l'ERP n'a rien à faire ici et n'y est nulle part.
 */
class WebShopController extends Controller
{
    public function __construct(
        private DeviceTokenRepository $deviceTokens
    ) {}

    #[Route('GET', '/webshop')]
    public function index(): void
    {
        $rules   = DeviceMode::rules();
        $base    = DeviceMode::webshopBase();
        $token   = DeviceMode::webshopToken();
        $blocker = $rules->webshopBlocker($base, $token);

        // Pas de repli : sans URL ou sans jeton, on dit lequel des deux manque
        // et on renvoie aux paramètres. Un cadre vide laisserait croire à une
        // panne du webshop.
        if ($blocker !== null) {
            $this->view('webshop/index', [
                'webshop_url'     => null,
                'webshop_blocker' => $blocker,
                'token_state'     => null,
            ]);
            return;
        }

        // Le jeton est révocable, et c'est le seul recours si la tablette
        // disparaît. On le vérifie à chaque ouverture de l'écran : une tablette
        // révoquée doit revenir à sa configuration, pas continuer d'afficher un
        // cadre dont on ne sait plus ce qu'il montre.
        $state = $this->deviceTokens->check(
            (string)$rules->webshopApiBase($base),
            $token
        );

        $this->view('webshop/index', [
            // Révoqué : rien n'est monté. Réseau injoignable : on ne peut pas
            // conclure, alors on ouvre quand même et on l'écrit — bloquer sur
            // un hoquet de wifi rendrait le comptoir inutilisable, et le
            // serveur du webshop refusera de toute façon un jeton révoqué.
            'webshop_url'     => $state === DeviceTokenRepository::REVOKED
                                    ? null
                                    : $rules->webshopUrl($base, $token),
            'webshop_blocker' => $state === DeviceTokenRepository::REVOKED ? 'revoked' : null,
            'token_state'     => $state,
        ]);
    }
}
