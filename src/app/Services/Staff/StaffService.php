<?php

namespace App\Kitchen\app\Services\Staff;

use App\Kitchen\app\Repositories\Staff\StaffRepository;
use App\Kitchen\core\Support\GlobalRegistry;

/**
 * Qui travaille aujourd'hui.
 *
 * Le PIN ne sort jamais d'ici : il ne sert qu'à la vérification serveur, dans
 * ChecklistService::completeTask(). Un écran n'a besoin que d'un nom et de
 * deux initiales.
 *
 * Voir docs/ENDPOINTS_EMPLOYES_PLANNING.md.
 */
class StaffService
{
    public function __construct(
        private StaffRepository $staffRepository
    ) {}

    private function getShopId(): int
    {
        return (int)(GlobalRegistry::get('user')['shop_id'] ?? 0);
    }

    /**
     * Toute l'équipe du magasin.
     *
     * @return array<int, array{id: mixed, name: string, initials: string, on_schedule: ?bool}>|null
     *         null = liste non servie
     */
    public function getEmployees(): ?array
    {
        $shopId = $this->getShopId();
        if ($shopId <= 0) {
            return null;
        }

        $employees = $this->staffRepository->getEmployees($shopId);
        if ($employees === null) {
            return null;
        }

        return array_map(fn(array $e) => [
            'id'          => $e['id'] ?? null,
            'name'        => (string)($e['name'] ?? ''),
            'initials'    => self::initials((string)($e['name'] ?? '')),
            'on_schedule' => self::readOnSchedule($e),
        ], $employees);
    }

    /**
     * Le personnel actif : ceux qui sont de service.
     *
     * Tant que l'API ne dit pas qui travaille, on rend toute l'équipe plutôt
     * qu'une liste vide — et `scheduleKnown()` permet à l'écran de l'écrire.
     * Un filtre annoncé mais inopérant trompe plus qu'il n'aide.
     *
     * @param array<int, array{on_schedule: ?bool}>|null $employees
     * @return array<int, array>
     */
    public function onDuty(?array $employees): array
    {
        if ($employees === null) {
            return [];
        }
        if (!$this->scheduleKnown($employees)) {
            return $employees;
        }
        return array_values(array_filter($employees, fn(array $e) => $e['on_schedule'] === true));
    }

    /** @param array<int, array{on_schedule: ?bool}>|null $employees */
    public function scheduleKnown(?array $employees): bool
    {
        foreach ($employees ?? [] as $e) {
            if ($e['on_schedule'] !== null) {
                return true;
            }
        }
        return false;
    }

    private static function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
    }

    /**
     * Lit l'indicateur de présence sous les noms de champs plausibles.
     * null quand aucun n'est fourni.
     */
    private static function readOnSchedule(array $e): ?bool
    {
        foreach (['on_schedule', 'is_on_schedule', 'on_shift', 'is_working', 'is_present', 'scheduled_today'] as $key) {
            if (array_key_exists($key, $e) && $e[$key] !== null && $e[$key] !== '') {
                return filter_var($e[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }
        return null;
    }
}
