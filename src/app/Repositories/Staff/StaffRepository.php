<?php

namespace App\Kitchen\app\Repositories\Staff;

use App\Kitchen\core\Http\ApiClient;

/**
 * L'équipe d'un magasin.
 *
 * Le même endpoint sert les checklists et la cuisson : savoir qui est en
 * atelier n'appartient à aucun des deux modules.
 */
class StaffRepository
{
    public function __construct(
        private ApiClient $apiClient
    ) {}

    /**
     * @return array<int, array<string, mixed>>|null
     *         null = l'API ne sert pas la liste. Distinct de [] — « personne
     *         n'est de service » et « on ne sait pas qui travaille » ne se
     *         disent pas pareil à l'écran.
     */
    public function getEmployees(int $shopId): ?array
    {
        $res = $this->apiClient->get("/shops/{$shopId}/employees");
        if (!($res['success'] ?? false) || !is_array($res['data'] ?? null)) {
            return null;
        }
        return $res['data'];
    }
}
