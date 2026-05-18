<?php

namespace App\Kitchen\app\Http\Controllers\Checklist;

use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\app\Services\Checklist\ChecklistService;

class ChecklistController extends Controller
{
    public function __construct(
        private ChecklistService $checklistService
    ) {}

    /**
     * GET /checklists
     * Widok przeglądania checklist i postępu zadań dla bieżącego sklepu.
     */
    public function index(): void
    {
        $date        = $_GET['date']         ?? date('Y-m-d');
        $checklistId = isset($_GET['checklist_id']) ? (int)$_GET['checklist_id'] : null;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > date('Y-m-d')) {
            $date = date('Y-m-d');
        }

        $checklists = $this->safeFetch(
            fn() => $this->checklistService->getChecklistsForShop($date),
            $this->errors,
            null,
            []
        );

        $progress = null;
        if ($checklistId && !empty($checklists)) {
            $progress = $this->safeFetch(
                fn() => $this->checklistService->getChecklistProgress($checklistId, $date),
                $this->errors,
                null,
                []
            );
        }

        $employees = $this->safeFetch(
            fn() => $this->checklistService->getEmployeesForShop(),
            $this->errors,
            null,
            []
        );

        $this->view('checklist/index', [
            'selected_date'         => $date,
            'selected_checklist_id' => $checklistId,
            'checklists'            => $checklists,
            'progress'              => $progress,
            'today'                 => date('Y-m-d'),
            'employees'             => $employees,
        ]);
    }

    /**
     * POST /checklists/tasks/{taskId}/complete
     * Weryfikuje PIN i oznacza zadanie jako wykonane. Zwraca JSON.
     */
    public function completeTask(string $taskId): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $taskId = (int)$taskId;
        if ($taskId <= 0) {
            return $this->json(['success' => false, 'message' => 'invalid_task'], 400);
        }

        $body       = $_POST;
        $employeeId = isset($body['employee_id']) ? (int)$body['employee_id'] : 0;
        $pin        = trim($body['pin'] ?? '');
        $date       = $body['date'] ?? date('Y-m-d');
        $note       = trim($body['note'] ?? '');
        $photo      = $_FILES['photo'] ?? null;

        if ($employeeId <= 0) {
            return $this->json(['success' => false, 'message' => 'employee_required'], 400);
        }

        if ($pin === '') {
            return $this->json(['success' => false, 'message' => 'pin_required'], 400);
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        $result = $this->checklistService->completeTask($taskId, $employeeId, $pin, $date, $note, $photo);

        $status = ($result['success'] ?? false) ? 200 : 422;
        return $this->json($result, $status);
    }
}
