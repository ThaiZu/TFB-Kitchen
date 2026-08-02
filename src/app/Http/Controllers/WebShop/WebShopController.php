<?php

namespace App\Kitchen\app\Http\Controllers\WebShop;

use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\core\Support\DeviceMode;
use App\Kitchen\core\Support\Route;

/**
 * Mode WebShop : on ouvre le back-office franchisé, on ne le réécrit pas.
 *
 * Le back-office existe, il est déployé, et compte 37 sections en 6 groupes.
 * Le refaire ici donnerait deux implémentations à maintenir, donc deux
 * comportements qui divergeraient — et il gère déjà tout ce qui est délicat :
 * pavé PIN, filtrage du menu par profil, cloisonnement à la boutique,
 * révocation immédiate, anti-brute-force. Kitchen lui donne un cadre et sa
 * boutique, rien de plus.
 *
 * Aucun jeton n'est transmis. Le back-office ouvre sa propre session PIN dans
 * le cadre ; le jeton admin de l'ERP n'a rien à faire sur une tablette et
 * n'apparaît nulle part ici.
 */
class WebShopController extends Controller
{
    #[Route('GET', '/webshop')]
    public function index(): void
    {
        $rules   = DeviceMode::rules();
        $base    = DeviceMode::webshopBase();
        $shopId  = DeviceMode::webshopShopId();
        $blocker = $rules->webshopBlocker($base, $shopId);

        // Pas de repli : sans URL ou sans boutique, on dit laquelle des deux
        // manque et on renvoie aux paramètres. Un cadre vide laisserait croire
        // à une panne du webshop.
        $this->view('webshop/index', [
            'webshop_url'     => $rules->webshopUrl($base, $shopId),
            'webshop_blocker' => $blocker,
            'webshop_shop_id' => $shopId,
        ]);
    }
}
