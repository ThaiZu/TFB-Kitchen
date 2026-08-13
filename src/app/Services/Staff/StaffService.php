<?php

namespace App\Kitchen\app\Services\Staff;

use App\Kitchen\app\Repositories\Staff\StaffRepository;
use App\Kitchen\core\Support\GlobalRegistry;

/**
 * Qui travaille aujourd'hui.
 *
 * Deux sources croisées : la liste des employés du franchisé, et le planning du
 * jour. Un employé est proposé pour signer une tâche s'il est ACTIF et s'il est
 * AU PLANNING de la date consultée. Le croisement se fait ici, sur des
 * identifiants, parce que le planning ne porte pas de noms.
 *
 * ── Rien n'est inventé ── (révision du 13/08/2026)
 * Si l'une des deux routes ne répond pas, on ne propose PERSONNE et l'écran
 * nomme la route à créer. On rendait auparavant toute l'équipe active « pour ne
 * pas bloquer » : c'était confortable et trompeur — un trou passait alors pour
 * un fonctionnement normal, et le back n'était jamais réclamé.
 *
 * Reste une distinction qui compte : un planning servi et VIDE n'est pas une
 * panne, c'est une réponse. Personne n'est de service ce jour-là, et l'écran le
 * dit dans ces mots — pas dans ceux d'une API manquante.
 *
 * Le PIN ne sort jamais d'ici : il ne sert qu'à la vérification serveur, dans
 * ChecklistService::verifyPin(), qui interroge sa propre source. Un écran n'a
 * besoin que d'un nom et de deux initiales.
 *
 * Le croisement et les filtres sont purs et vérifiés sans réseau —
 * bin/staff-test.php. Voir docs/ENDPOINTS_EMPLOYES_PLANNING.md.
 */
class StaffService
{
    public function __construct(
        private StaffRepository $staffRepository
    ) {}

    /**
     * La route qui n'a pas répondu au dernier appel, ou null.
     *
     * L'écran l'affiche telle quelle. Depuis le 13/08/2026, on ne remplace plus
     * une réponse manquante par une liste plausible : on nomme la route.
     */
    private ?string $missing = null;

    public function missingApi(): ?string
    {
        return $this->missing;
    }

    private function getShopId(): int
    {
        return (int)(GlobalRegistry::get('user')['shop_id'] ?? 0);
    }

    /**
     * L'équipe active, chacun marqué présent ou non au planning de `$date`.
     *
     * @param string|null $date  Jour consulté (Y-m-d). Sans date, on ne demande
     *                           pas le planning : la question « qui est de
     *                           service » n'a pas de sens hors d'un jour.
     *
     * @return array<int, array{id: mixed, name: string, initials: string, on_schedule: ?bool}>|null
     *         null = liste non servie
     */
    public function getEmployees(?string $date = null): ?array
    {
        $this->missing = null;

        $shopId = $this->getShopId();
        if ($shopId <= 0) {
            return null;
        }

        $employees = $this->staffRepository->getEmployees($shopId);
        if ($employees === null) {
            $this->missing = 'GET /franchisee-employees';
            return null;
        }

        $employees = self::activeOnly($employees);

        // Sans date, la question « qui est de service » n'a pas de sens : ce
        // n'est pas une route manquante, c'est un appel qui ne la pose pas.
        if ($date === null || $date === '') {
            return array_map(fn(array $e) => self::card($e, null), $employees);
        }

        $rows = $this->staffRepository->getSchedule($shopId, $date);
        if ($rows === null) {
            $this->missing = 'GET /shops/{id}/schedule?date=' . $date;
            return array_map(fn(array $e) => self::card($e, null), $employees);
        }

        $scheduled = self::scheduledIds($rows, $date);

        return array_map(
            fn(array $e) => self::card($e, in_array((string)($e['id'] ?? ''), $scheduled, true)),
            $employees
        );
    }

    /** @return array{id: mixed, name: string, initials: string, on_schedule: ?bool} */
    private static function card(array $e, ?bool $onSchedule): array
    {
        return [
            'id'          => $e['id'] ?? null,
            'name'        => (string)($e['name'] ?? ''),
            'initials'    => self::initials((string)($e['name'] ?? '')),
            'on_schedule' => $onSchedule,
        ];
    }

    /**
     * Qui proposer, et sous quelle réserve.
     *
     * @param array<int, array{on_schedule: ?bool}>|null $employees
     * @return array{list: array<int, array>, mode: string, missing: ?string}
     *         mode = scheduled — le planning désigne ces personnes
     *              | empty     — planning servi, personne de service ce jour-là
     *              | missing    — une route n'a pas répondu ; `missing` la nomme
     *              | none       — aucun employé actif
     */
    public function roster(?array $employees): array
    {
        if ($this->missing !== null) {
            // La route ne répond pas : on ne propose personne et on dit
            // laquelle créer. Proposer toute l'équipe ferait passer un trou
            // pour un fonctionnement normal.
            return ['list' => [], 'mode' => 'missing', 'missing' => $this->missing];
        }

        if ($employees === null || $employees === []) {
            return ['list' => [], 'mode' => 'none', 'missing' => null];
        }

        $onDuty = array_values(array_filter($employees, fn(array $e) => $e['on_schedule'] === true));

        // Planning servi et vide : ce n'est pas une panne, c'est une réponse.
        // Personne n'est de service ce jour-là, et l'écran le dit.
        return [
            'list'    => $onDuty,
            'mode'    => $onDuty === [] ? 'empty' : 'scheduled',
            'missing' => null,
        ];
    }

    /**
     * Le personnel de service, liste seule.
     *
     * Conservé pour la cuisson, qui affiche une équipe sans avoir à expliquer
     * pourquoi. L'écran des checklists, lui, doit dire sous quelle réserve il
     * propose sa liste : il passe par roster().
     *
     * @param array<int, array{on_schedule: ?bool}>|null $employees
     * @return array<int, array>
     */
    public function onDuty(?array $employees): array
    {
        return $this->roster($employees)['list'];
    }

    /** Le planning a-t-il répondu ? Sert aux écrans qui n'affichent pas la raison. */
    public function scheduleServed(): bool
    {
        return $this->missing === null;
    }

    /**
     * Écarte les employés désactivés.
     *
     * Prudence volontaire : on n'écarte que ce qui est EXPLICITEMENT inactif.
     * Une fiche sans indicateur reste dans la liste — sortir quelqu'un sur une
     * absence d'information ferait disparaître l'équipe entière le jour où le
     * champ change de nom.
     *
     * @param array<int, array<string, mixed>> $employees
     * @return array<int, array<string, mixed>>
     */
    public static function activeOnly(array $employees): array
    {
        return array_values(array_filter($employees, static function (array $e): bool {
            foreach (['is_active', 'active', 'enabled'] as $k) {
                if (array_key_exists($k, $e) && $e[$k] !== null && $e[$k] !== '') {
                    return filter_var($e[$k], FILTER_VALIDATE_BOOLEAN);
                }
            }
            foreach (['deleted_at', 'archived_at'] as $k) {
                if (!empty($e[$k])) {
                    return false;
                }
            }
            if (isset($e['status']) && is_string($e['status'])) {
                $s = strtoupper(trim($e['status']));
                if (in_array($s, ['INACTIVE', 'DISABLED', 'ARCHIVED', 'DELETED', 'LEFT'], true)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Les identifiants d'employés présents au planning, en chaînes.
     *
     * Comparer en chaînes est délibéré : l'API rend tantôt 12, tantôt "12", et
     * une comparaison typée ferait disparaître la moitié de l'équipe sans rien
     * signaler.
     *
     * Une ligne datée d'un autre jour est écartée même si l'endpoint est censé
     * filtrer : c'est une question de service, pas de confiance — une ligne de
     * la veille laissée passer ferait signer quelqu'un qui n'était pas là.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, string>
     */
    public static function scheduledIds(array $rows, string $date): array
    {
        $ids = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            foreach (['date', 'day', 'work_date', 'scheduled_for_date'] as $k) {
                if (!empty($row[$k]) && is_string($row[$k])) {
                    if (substr(trim($row[$k]), 0, 10) !== $date) {
                        continue 2;
                    }
                    break;
                }
            }

            foreach (['employee_id', 'franchisee_employee_id', 'id_employee', 'id_franchisee_employee', 'user_id'] as $k) {
                if (isset($row[$k]) && $row[$k] !== '') {
                    $ids[] = (string)$row[$k];
                    continue 2;
                }
            }

            // Un planning peut aussi imbriquer la fiche complète.
            if (isset($row['employee']) && is_array($row['employee']) && isset($row['employee']['id'])) {
                $ids[] = (string)$row['employee']['id'];
            }
        }

        return array_values(array_unique($ids));
    }

    private static function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
    }

}
