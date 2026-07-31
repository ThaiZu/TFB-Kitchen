<?php

namespace App\Kitchen\app\Services\Checklist;

use App\Kitchen\app\Repositories\Checklist\ChecklistRepository;
use App\Kitchen\core\Support\GlobalRegistry;

class ChecklistService
{
    public function __construct(private ChecklistRepository $checklistRepository) {}

    public function getChecklistsForShop(string $date): array
    {
        $shopId = $this->getShopId();
        if ($shopId <= 0) {
            return [];
        }
        return $this->checklistRepository->getChecklistsForShop($shopId, $date);
    }

    public function getChecklistProgress(int $checklistId, string $date): array
    {
        $shopId = $this->getShopId();
        if ($shopId <= 0) {
            return [];
        }
        return $this->checklistRepository->getChecklistProgress($shopId, $checklistId, $date);
    }

    /**
     * Zwraca listę pracowników sklepu tylko z polami id i name (bez PIN).
     */
    public function getEmployeesForShop(): array
    {
        $shopId = $this->getShopId();
        if ($shopId <= 0) {
            return [];
        }
        $employees = $this->checklistRepository->getEmployeesForShop($shopId);

        return array_map(function (array $e) {
            // Le PIN ne sort jamais d'ici : il ne sert qu'à la vérification
            // serveur, dans completeTask().
            $name = (string)($e['name'] ?? '');
            $parts = preg_split('/\s+/', trim($name)) ?: [];
            $initials = mb_strtoupper(
                mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1)
            );

            return [
                'id'          => $e['id'],
                'name'        => $name,
                'initials'    => $initials,
                // Présence du jour. L'API ne renvoie peut-être aucun de ces
                // champs : on garde null pour distinguer « pas de service »
                // de « information non fournie », et l'écran le dit au lieu
                // de masquer tout le monde.
                'on_schedule' => self::readOnSchedule($e),
            ];
        }, $employees);
    }

    /**
     * Lit l'indicateur de présence sous les noms de champs plausibles.
     * Retourne null si aucun n'est fourni.
     *
     * Voir docs/ENDPOINTS_EMPLOYES_PLANNING.md.
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

    /**
     * Weryfikuje PIN pracownika po stronie serwera, a następnie oznacza zadanie jako wykonane.
     * Zwraca ['success' => bool, 'message' => string].
     */
    public function completeTask(int $taskId, int $employeeId, string $pin, string $date, string $note, ?array $photo = null): array
    {
        $shopId = $this->getShopId();
        if ($shopId <= 0) {
            return ['success' => false, 'message' => 'shop_not_found'];
        }

        // Pobierz pracowników ze szczegółami (z PIN) do weryfikacji
        $employees = $this->checklistRepository->getEmployeesForShop($shopId);
        $employee  = null;
        foreach ($employees as $e) {
            if ((int)$e['id'] === $employeeId) {
                $employee = $e;
                break;
            }
        }

        if ($employee === null) {
            return ['success' => false, 'message' => 'employee_not_found'];
        }

        if (($employee['pin'] ?? '') !== $pin) {
            return ['success' => false, 'message' => 'invalid_pin'];
        }

        $fields = [
            'task_id'            => $taskId,
            'status'             => 'DONE',
            'scheduled_for_date' => $date,
            'employee_id'        => $employeeId,
            'note'               => $note,
        ];

        $result = $this->checklistRepository->markTaskDone($employeeId, $taskId, $fields, $photo);

        return [
            'success' => $result['success'] ?? false,
            'message' => $result['message'] ?? ($result['description'] ?? 'error'),
        ];
    }

    private function getShopId(): int
    {
        return (int)(GlobalRegistry::get('user')['shop_id'] ?? 0);
    }
}
