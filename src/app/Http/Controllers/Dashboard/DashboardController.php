<?php
namespace App\Kitchen\app\Http\Controllers\Dashboard;
use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\app\Services\Checklist\ChecklistService;
use App\Kitchen\core\Support\Route;
use App\Kitchen\core\Support\ShiftSession;
class DashboardController extends Controller
{
    public function __construct(
        private ChecklistService $checklistService
    ) {}
    #[Route('GET', '/dashboard')]
    public function index()
    {
        $today = date('Y-m-d');
        $shopId = $this->checklistService->shopId();
        $shift = $shopId > 0 ? ShiftSession::current($shopId, $today) : null;
        $entryEmployees = [];
        if ($shift === null) {
            $employees = $this->safeFetch(
                fn() => $this->checklistService->getEmployeesForShop($today),
                $this->warnings,
                null,
                []
            );
            $entryEmployees = $this->checklistService->roster($employees)['list'];
        }
        $this->view("dashboard/dashboard", [
            'entry_employees' => $entryEmployees,
            'has_active_shift' => $shift !== null,
        ]);
    }
}
