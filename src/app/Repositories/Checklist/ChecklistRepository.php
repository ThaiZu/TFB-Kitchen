<?php

namespace App\Kitchen\app\Repositories\Checklist;

use App\Kitchen\core\Http\ApiClient;

class ChecklistRepository
{
    public function __construct(private ApiClient $apiClient) {}

    /**
     * Pobiera checklisty aktywne dla sklepu w danym dniu.
     */
    public function getChecklistsForShop(int $shopId, string $date): array
    {
        $res = $this->apiClient->get("/consultant/shops/{$shopId}/checklists?date={$date}");
        return ($res['success'] ?? false) && isset($res['data']) ? $res['data'] : [];
    }

    /**
     * Pobiera postęp realizacji checklisty dla sklepu i dnia.
     */
    public function getChecklistProgress(int $shopId, int $checklistId, string $date): array
    {
        $res = $this->apiClient->get("/consultant/shops/{$shopId}/checklists/{$checklistId}/progress?date={$date}");
        return ($res['success'] ?? false) && isset($res['data']) ? $res['data'] : [];
    }

    /**
     * Pobiera pracowników sklepu (z polem pin do weryfikacji po stronie serwera).
     */
    public function getEmployeesForShop(int $shopId): array
    {
        $res = $this->apiClient->get("/shops/{$shopId}/employees");
        return ($res['success'] ?? false) && isset($res['data']) ? $res['data'] : [];
    }

    /**
     * Oznacza zadanie jako wykonane przez pracownika.
     */
    public function markTaskDone(int $employeeId, int $taskId, array $fields, ?array $photo = null): array
    {
        $files = [];
        if ($photo && isset($photo['tmp_name']) && $photo['error'] === UPLOAD_ERR_OK) {
            $files['photo'] = $photo;
        }

        $res = $this->apiClient->postMultipart(
            "/employees/{$employeeId}/tasks/{$taskId}/mark-as-done",
            $fields,
            $files
        );
        return $res;
    }
}
