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
     * Zwraca listę pracowników sklepu tylko z polami id i name (bez PIN).
     *
     * La cuisson pose la même question — qui est en atelier — et la réponse ne
     * doit exister qu'à un endroit : StaffService. Ici on garde la signature
     * (liste, jamais null) attendue par l'écran des checklists.
     */
    public function getEmployeesForShop(): array
    {
        return $this->staffService->getEmployees() ?? [];
    }

    /**
     * Weryfikuje PIN pracownika po stronie serwera, a następnie oznacza zadanie jako wykonane.
     * Zwraca ['success' => bool, 'message' => string].
     */
    public function completeTask(int $taskId, int $employeeId, string $pin, string $date, string $note, ?array $photo = null, bool $shiftVerified = false): array
    {
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
