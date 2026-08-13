<?php

namespace App\Kitchen\app\Repositories\Staff;

use App\Kitchen\core\Http\ApiClient;

/**
 * L'équipe d'un magasin, et qui y travaille aujourd'hui.
 *
 * Deux questions distinctes, donc deux endpoints :
 *
 *   • `/franchisee-employees` — QUI EXISTE. La fiche de chaque employé du
 *     franchisé, avec son état d'activité. C'est la liste de référence.
 *   • `/shops/{id}/schedule`  — QUI TRAVAILLE, un jour donné. Le planning ne
 *     porte pas de noms mais des identifiants : c'est le croisement des deux
 *     qui donne « qui peut signer une tâche aujourd'hui ».
 *
 * Le même couple sert les checklists et la cuisson : savoir qui est en atelier
 * n'appartient à aucun des deux modules.
 *
 * ── Aucun repli ──
 * Si l'une des deux routes ne répond pas, on rend null. L'écran nomme alors la
 * route manquante — « API à créer : GET /shops/{id}/schedule » — au lieu de
 * proposer une liste tirée d'ailleurs. Pendant que le back se construit, une
 * liste plausible venue d'une autre source masque précisément le trou qu'on
 * cherche.
 */
class StaffRepository
{
    public function __construct(
        private ApiClient $apiClient
    ) {}

    /**
     * Les employés du franchisé.
     *
     * @return array<int, array<string, mixed>>|null
     *         null = l'API ne sert pas la liste. Distinct de [] — « personne
     *         n'est de service » et « on ne sait pas qui travaille » ne se
     *         disent pas pareil à l'écran.
     */
    public function getEmployees(int $shopId): ?array
    {
        // Une seule adresse. Le repli sur /shops/{id}/employees a été retiré le
        // 13/08/2026 : deux routes pour une même question, c'est une réponse
        // qui peut venir de deux endroits sans qu'on sache lequel — et c'est
        // exactement ce qui empêche de voir qu'une des deux ne répond pas.
        return $this->rows($this->apiClient->get('/franchisee-employees'));
    }

    /**
     * Le planning d'un jour.
     *
     * @return array<int, array<string, mixed>>|null
     *         null = planning non servi — à ne pas confondre avec [], qui veut
     *         dire « personne n'est de service ce jour-là ».
     */
    public function getSchedule(int $shopId, string $date): ?array
    {
        return $this->rows($this->apiClient->where("/shops/{$shopId}/schedule", ['date' => $date]));
    }

    /**
     * Extrait la liste d'une réponse, quelle que soit la façon dont elle est
     * emballée : `data` peut être la liste, ou la porter sous `items`,
     * `employees`, `schedule`… On ne devine pas au-delà de ces noms — un
     * emballage inconnu rend null, et l'écran le dit.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function rows(array $res): ?array
    {
        if (!($res['success'] ?? false)) {
            return null;
        }

        $data = $res['data'] ?? null;
        if (!is_array($data)) {
            return null;
        }

        foreach (['items', 'employees', 'schedule', 'rows', 'data'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                $data = $data[$key];
                break;
            }
        }

        // Une liste, pas un objet : des clés 0,1,2… et des entrées tableau.
        $list = array_values(array_filter($data, 'is_array'));

        return $list === [] && $data !== [] ? null : $list;
    }
}
