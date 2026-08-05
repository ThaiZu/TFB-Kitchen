<?php

namespace App\Kitchen\app\Http\Controllers\Checklist;

use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\app\Services\Checklist\ChecklistFocusService;
use App\Kitchen\app\Services\Checklist\ChecklistService;
use App\Kitchen\core\Support\ShiftSession;

class ChecklistController extends Controller
{
    public function __construct(
        private ChecklistService $checklistService,
        private ChecklistFocusService $focus
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

        // ── Ouvrir la checklist du moment, plutôt que de la demander ──
        // Le paramètre ABSENT et le paramètre VIDE ne veulent pas dire la même
        // chose : absent, c'est une arrivée sur l'écran, et on peut décider
        // pour l'équipe ; vide, c'est « — toutes — » choisi à la main, et on ne
        // repasse pas par-dessus. La règle ne vaut qu'aujourd'hui : sur une
        // date passée on vient relire, pas exécuter.
        $autoFocus = false;
        if (!array_key_exists('checklist_id', $_GET) && $date === date('Y-m-d') && $checklists) {
            $picked = $this->focus->pick($checklists, date('H:i'));
            if ($picked !== null) {
                $checklistId = $picked;
                $autoFocus   = true;
            }
        }

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
            // La vue s'en sert pour deux choses : replier les filtres, et dire
            // que c'est elle qui a choisi. Une sélection qu'on n'a pas faite et
            // qu'on ne voit pas expliquée passe pour un bug.
            'auto_focus'            => $autoFocus,
            // Le nom de la checklist retenue, pour que le bandeau replié dise
            // ce qu'il cache.
            'focused_name'          => $this->nameOf($checklists, $checklistId),
            'checklists'            => $checklists,
            'progress'              => $progress,
            'today'                 => date('Y-m-d'),
            'employees'             => $employees,
        ]);
    }

    /** Le nom d'une checklist par son identifiant, ou null. */
    private function nameOf(array $checklists, ?int $id): ?string
    {
        if (!$id) {
            return null;
        }
        foreach ($checklists as $c) {
            if (is_array($c) && (int)($c['id'] ?? 0) === $id) {
                return (string)($c['name'] ?? '') ?: null;
            }
        }

        return null;
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
