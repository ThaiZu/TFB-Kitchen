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
 * ── Ce qui ne change pas, et pourquoi ──
 * Quand le planning n'est pas servi, on rend toute l'équipe active plutôt
 * qu'une liste vide, et `scheduleKnown()` permet à l'écran de l'écrire. Un
 * filtre annoncé mais inopérant trompe plus qu'il n'aide ; une liste vide,
 * elle, rendrait la checklist inachevable. La distinction entre `null` (« on
 * ne sait pas ») et `false` (« pas de service ») est donc portée jusqu'à la
 * vue.
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
        $shopId = $this->getShopId();
        if ($shopId <= 0) {
            return null;
        }

        $employees = $this->staffRepository->getEmployees($shopId);
        if ($employees === null) {
            return null;
        }

        $employees = self::activeOnly($employees);

        // Le planning n'est demandé que si l'on a une date. Son absence n'est
        // pas une erreur : elle laisse `on_schedule` à null, et l'écran dira
        // qu'il ne connaît pas le service du jour.
        $scheduled = null;
        if ($date !== null && $date !== '') {
            $rows = $this->staffRepository->getSchedule($shopId, $date);
            if ($rows !== null) {
                $scheduled = self::scheduledIds($rows, $date);
            }
        }

        return array_map(fn(array $e) => [
            'id'          => $e['id'] ?? null,
            'name'        => (string)($e['name'] ?? ''),
            'initials'    => self::initials((string)($e['name'] ?? '')),
            'on_schedule' => $scheduled === null
                ? self::readOnSchedule($e)
                : in_array((string)($e['id'] ?? ''), $scheduled, true),
        ], $employees);
    }

    /**
     * Qui proposer, et sous quelle réserve.
     *
     * ── La règle, et sa limite ──
     * On filtre par le planning quand il désigne quelqu'un. Quand il ne désigne
     * PERSONNE, on rend toute l'équipe active en le disant.
     *
     * Ce dernier cas n'est pas un détail : un planning vide — pas encore saisi,
     * saisi ailleurs, ou simplement absent pour un jour férié travaillé — videra
     * la liste, et une liste vide rend toutes les tâches invalidables. Le
     * magasin est ouvert, l'équipe est là, et l'écran refuserait de la laisser
     * signer. Un filtre ne doit jamais rendre le travail impossible ; il doit
     * aider quand il sait, et s'effacer quand il ne sait pas.
     *
     * @param array<int, array{on_schedule: ?bool}>|null $employees
     * @return array{list: array<int, array>, mode: string}
     *         mode = scheduled   — le planning désigne ces personnes
     *              | all_unknown — planning non servi, toute l'équipe
     *              | all_empty   — planning servi mais vide, toute l'équipe
     *              | none        — aucune équipe du tout
     */
    public function roster(?array $employees): array
    {
        if ($employees === null || $employees === []) {
            return ['list' => [], 'mode' => 'none'];
        }

        if (!$this->scheduleKnown($employees)) {
            return ['list' => $employees, 'mode' => 'all_unknown'];
        }

        $onDuty = array_values(array_filter($employees, fn(array $e) => $e['on_schedule'] === true));
        if ($onDuty === []) {
            return ['list' => $employees, 'mode' => 'all_empty'];
        }

        return ['list' => $onDuty, 'mode' => 'scheduled'];
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

    /** @param array<int, array{on_schedule: ?bool}>|null $employees */
    public function scheduleKnown(?array $employees): bool
    {
        foreach ($employees ?? [] as $e) {
            if ($e['on_schedule'] !== null) {
                return true;
            }
        }
        return false;
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

    /**
     * Lit l'indicateur de présence porté par la fiche elle-même, quand aucun
     * planning n'est disponible. null quand aucun n'est fourni.
     */
    private static function readOnSchedule(array $e): ?bool
    {
        foreach (['on_schedule', 'is_on_schedule', 'on_shift', 'is_working', 'is_present', 'scheduled_today'] as $key) {
            if (array_key_exists($key, $e) && $e[$key] !== null && $e[$key] !== '') {
                return filter_var($e[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }
        return null;
    }
}
