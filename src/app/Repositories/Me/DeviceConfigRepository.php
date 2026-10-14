<?php

namespace App\Kitchen\app\Repositories\Me;

use App\Kitchen\core\Http\ApiClient;

/**
 * Ce que la tablette a le droit d'afficher, par mode.
 *
 * Alimenté par la table `pwa_kitchen_param` côté ERP : une ligne par couple
 * (mode, fonctionnalité), avec son état et sa place. L'endpoint assemble ces
 * lignes en une réponse que la PWA lit telle quelle — voir
 * docs/BACKEND_A_FAIRE.md §8.
 *
 * ── Pourquoi une table plutôt qu'une constante ──
 * Ce que montre chaque mode se décidait dans le code, donc changeait par un
 * déploiement. Ouvrir les réclamations au comptoir demandait un commit, une
 * revue et une mise en production, pour un choix qui appartient au franchisé.
 * La table déplace ce choix là où il se prend.
 *
 * ── Ce que ça ne fait pas ──
 * Ce n'est pas un contrôle d'accès. Retirer une entrée retire un chemin
 * d'accès, pas le droit d'y aller : les routes restent servies, et un mode
 * forgé n'ouvre rien de plus. La sécurité tient au jeton de session, comme
 * avant. C'est un réglage d'ergonomie, et il ne faut pas le vendre pour autre
 * chose.
 */
class DeviceConfigRepository
{
    public function __construct(
        private ApiClient $apiClient
    ) {}

    /**
     * @return array|null null = configuration non servie. L'appelant garde
     *                    alors les valeurs par défaut de l'application, plutôt
     *                    qu'un menu vide.
     */
    public function get(): ?array
    {
        $res = $this->apiClient->get('/devices/me/config');
        if (!($res['success'] ?? false) || !is_array($res['data'] ?? null)) {
            return null;
        }

        return $res['data'];
    }
}
