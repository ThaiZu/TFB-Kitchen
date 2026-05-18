<?php
namespace App\Kitchen\app\Http\Controllers\Dashboard;
use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\app\Services\Checklist\ChecklistService;
use App\Kitchen\core\Support\Route;
class DashboardController extends Controller
{
    public function __construct(
        private ChecklistService $checklistService
    ) {}
    #[Route('GET', '/dashboard')]
    public function index()
    {
        $today = date('Y-m-d');
        $checklists = $this->safeFetch(
            fn() => $this->checklistService->getChecklistsForShop($today),
            $this->errors,
            null,
            []
        );
        $tasksCompleted = 0;
        $tasksTotal     = 0;
        foreach ($checklists as $cl) {
            $tasksCompleted += (int)($cl['tasks_done']  ?? 0);
            $tasksTotal     += (int)($cl['tasks_total'] ?? 0);
        }
        $tasksTodo = max(0, $tasksTotal - $tasksCompleted);
        $this->view("dashboard/dashboard", [
            'stats' => [
                'tasks_todo'        => $tasksTodo,
                'tasks_in_progress' => 0,
                'tasks_completed'   => $tasksCompleted,
            ],
        ]);
    }
}
