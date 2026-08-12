<?php

namespace App\Kitchen\app\Services\Checklist;

use App\Kitchen\app\Repositories\Checklist\ChecklistRepository;
use App\Kitchen\app\Services\Staff\StaffService;
use App\Kitchen\core\Support\GlobalRegistry;

class ChecklistService
{
    public function __construct(
        private ChecklistRepository $checklistRepository,
        private StaffService $staffService
    ) {}

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
     * Qui peut signer une tâche, le jour consulté.
     *
     * La liste ne propose plus toute l'équipe mais les personnes ACTIVES et AU
     * PLANNING de cette date : proposer quelqu'un qui n'était pas là invite à
     * signer sous son nom, et le relevé perd sa valeur de preuve. La date
     * compte — une checklist se relit aussi pour hier.
     *
     * Le tableau rendu porte `on_schedule` à null quand le planning n'est pas
     * servi : l'écran affiche alors toute l'équipe en l'écrivant, plutôt qu'une
     * liste vide qui rendrait la checklist inachevable.
     *
     * @return array<int, array{id: mixed, name: string, initials: string, on_schedule: ?bool}>
     */
    public function getEmployeesForShop(?string $date = null): array
    {
        return $this->staffService->getEmployees($date) ?? [];
    }

    /** L'équipe réellement proposable : filtrée par le planning s'il est connu. */
    public function onDuty(array $employees): array
    {
        return $this->staffService->onDuty($employees);
    }

    /** Le planning du jour est-il connu ? L'écran le dit à l'équipe. */
    public function scheduleKnown(array $employees): bool
    {
        return $this->staffService->scheduleKnown($employees);
    }

    /**
     * Weryfikuje PIN pracownika po stronie serwera, a następnie oznacza zadanie jako wykonane.
     * Zwraca ['success' => bool, 'message' => string].
     */
    public function completeTask(int $taskId, int $employeeId, string $pin, string $date, string $note, ?array $photo = null, bool $shiftVerified = false, string $taskStatus = 'DONE'): array
    {
        // Le contrôleur a déjà refusé tout autre statut ; on referme la liste
        // ici aussi, parce que ce service est appelable d'ailleurs et qu'un
        // statut inventé s'écrirait tel quel dans le relevé.
        if (!in_array($taskStatus, ['DONE', 'FAILED'], true)) {
            $taskStatus = 'DONE';
        }

        $shopId = $this->getShopId();
        if ($shopId <= 0) {
            return ['success' => false, 'message' => 'shop_not_found'];
        }

        // Poste déjà ouvert : le PIN a été vérifié à la prise de poste, et le
        // porteur vient d'un cookie signé par le serveur — pas d'un champ du
        // formulaire. On ne le redemande donc pas à chaque tâche.
        //
        // `$shiftVerified` n'est JAMAIS renseigné depuis la requête : le
        // contrôleur le déduit du cookie qu'il a lui-même validé. Sans cette
        // règle, il suffirait d'ajouter un champ au formulaire pour sauter la
        // vérification.
        if (!$shiftVerified) {
            $verified = $this->verifyPin($employeeId, $pin);
            if (!($verified['success'] ?? false)) {
                return $verified;
            }
        }

        $fields = [
            'task_id'            => $taskId,
            'status'             => $taskStatus,
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

    /**
     * Vérifie le PIN d'un employé, et rien d'autre.
     *
     * Extrait de completeTask() parce que la prise de poste en a besoin aussi :
     * deux copies de ce contrôle finiraient par diverger, et c'est le seul
     * endroit qui décide si une signature est vraie.
     *
     * @return array{success:bool,message:string,name?:string}
     */
    public function verifyPin(int $employeeId, string $pin): array
    {
        $shopId = $this->getShopId();
        if ($shopId <= 0) {
            return ['success' => false, 'message' => 'shop_not_found'];
        }

        foreach ($this->checklistRepository->getEmployeesForShop($shopId) as $e) {
            if ((int) $e['id'] !== $employeeId) {
                continue;
            }
            // hash_equals : comparer deux codes courts caractère par caractère
            // laisse la durée de la réponse renseigner sur les chiffres justes.
            if (!hash_equals((string) ($e['pin'] ?? ''), $pin)) {
                return ['success' => false, 'message' => 'invalid_pin'];
            }

            return ['success' => true, 'message' => 'ok', 'name' => (string) ($e['name'] ?? '')];
        }

        return ['success' => false, 'message' => 'employee_not_found'];
    }

    private function getShopId(): int
    {
        return (int)(GlobalRegistry::get('user')['shop_id'] ?? 0);
    }
}
