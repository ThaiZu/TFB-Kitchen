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
 * ── Pourquoi pas un seul appel ──
 * `/shops/{id}/employees` répondait déjà, mais sans notion de date : il donne
 * l'équipe, pas le service du jour. Une checklist se relit aussi pour hier, et
 * il faut alors savoir qui était là CE jour-là. Il reste ici en dernier
 * recours, pour qu'une indisponibilité de `/franchisee-employees` ne vide pas
 * la modale et ne rende pas les tâches invalidables.
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
        $rows = $this->rows($this->apiClient->get('/franchisee-employees'));
        if ($rows !== null) {
            return $rows;
        }

        // Repli sur l'ancien endpoint : c'est un repli d'ADRESSE, pas de
        // données. On ne fabrique rien — soit une des deux routes répond, soit
        // on rend null et l'écran dit qu'il ne sait pas.
        return $this->rows($this->apiClient->get("/shops/{$shopId}/employees"));
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
