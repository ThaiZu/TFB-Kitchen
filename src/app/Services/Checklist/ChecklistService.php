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
        return array_map(fn($e) => ['id' => $e['id'], 'name' => $e['name']], $employees);
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
