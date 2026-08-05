<?php

namespace App\Kitchen\app\Http\Controllers\Checklist;

use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\app\Services\Checklist\ChecklistService;
use App\Kitchen\core\Support\ShiftSession;

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

        // Le poste ouvert, s'il y en a un : c'est lui qui décide si l'écran
        // demande encore un nom et un code, ou s'il enchaîne.
        $shift = ShiftSession::current();

        $this->view('checklist/index', [
            'shift'                 => $shift ? [
                'name'      => $shift['name'],
                'initials'  => ShiftSession::rules()->initials($shift['name']),
                'remaining' => ShiftSession::rules()->remaining($shift, time()),
            ] : null,
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

        $body  = $_POST;
        $date  = $body['date'] ?? date('Y-m-d');
        $note  = trim($body['note'] ?? '');
        $photo = $_FILES['photo'] ?? null;

        /* ── Qui signe ──
           Le poste ouvert fait foi, et il vient du cookie signé par le serveur.
           On ne lit PAS l'employé envoyé par le formulaire quand un poste est
           ouvert : sinon il suffirait de changer un champ pour signer sous le
           nom d'un collègue, et la prise de poste ne protégerait rien. */
        $shift = ShiftSession::current();

        if ($shift) {
            $employeeId = (int) $shift['id'];
            $pin        = '';
        } else {
            $employeeId = isset($body['employee_id']) ? (int) $body['employee_id'] : 0;
            $pin        = trim($body['pin'] ?? '');

            if ($employeeId <= 0) {
                return $this->json(['success' => false, 'message' => 'employee_required'], 400);
            }
            if ($pin === '') {
                return $this->json(['success' => false, 'message' => 'pin_required'], 400);
            }
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        $result = $this->checklistService->completeTask(
            $taskId, $employeeId, $pin, $date, $note, $photo, $shift !== null
        );

        // Une tâche validée repousse l'échéance : c'est ce qui permet
        // d'enchaîner une checklist longue sans que le poste se referme au
        // milieu. Seulement si elle a réussi — un échec n'est pas un geste.
        if ($shift && ($result['success'] ?? false)) {
            ShiftSession::touch($shift);
        }

        $status = ($result['success'] ?? false) ? 200 : 422;
        return $this->json($result, $status);
    }

    /**
     * POST /ajax/checklists/shift — prendre son poste.
     *
     * Le seul endroit où le PIN est saisi. Ensuite, toute la checklist
     * s'enchaîne.
     */
    #[Route('POST', '/ajax/checklists/shift')]
    public function openShift(): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $employeeId = isset($_POST['employee_id']) ? (int) $_POST['employee_id'] : 0;
        $pin        = trim($_POST['pin'] ?? '');

        if ($employeeId <= 0 || $pin === '') {
            return $this->json(['success' => false, 'message' => 'employee_and_pin_required'], 400);
        }

        $res = $this->checklistService->verifyPin($employeeId, $pin);
        if (!($res['success'] ?? false)) {
            return $this->json($res, 422);
        }

        ShiftSession::open($employeeId, $res['name'] ?? '');
        $claims = ShiftSession::current();

        return $this->json([
            'success'   => true,
            'name'      => $res['name'] ?? '',
            'initials'  => ShiftSession::rules()->initials($res['name'] ?? ''),
            'remaining' => $claims ? ShiftSession::rules()->remaining($claims, time()) : 0,
        ]);
    }

    /** POST /ajax/checklists/shift/close — passer la main. */
    #[Route('POST', '/ajax/checklists/shift/close')]
    public function closeShift(): \Symfony\Component\HttpFoundation\JsonResponse
    {
        ShiftSession::close();

        return $this->json(['success' => true]);
    }
}
