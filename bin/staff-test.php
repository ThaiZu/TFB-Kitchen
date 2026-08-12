<?php
/**
 * Vérifie le croisement employés × planning, sans serveur ni réseau.
 *
 *     php bin/staff-test.php
 *
 * Ce croisement décide qui peut signer une tâche. Ce qui doit tenir ici n'est
 * pas le cas nominal — c'est qu'on n'écarte JAMAIS quelqu'un sur une absence
 * d'information, et qu'un identifiant rendu tantôt en nombre tantôt en chaîne
 * ne fasse pas disparaître la moitié de l'équipe. Une faute à cet endroit se
 * voit le jour où personne ne peut plus valider l'ouverture du magasin.
 */
require __DIR__ . '/../vendor/autoload.php';

use App\Kitchen\app\Services\Staff\StaffService;

$ok = 0;
$ko = [];
function check(string $what, $got, $want): void
{
    global $ok, $ko;
    if ($got === $want) { $ok++; return; }
    $ko[] = sprintf("  ✗ %s\n      attendu : %s\n      obtenu  : %s",
        $what, json_encode($want, JSON_UNESCAPED_UNICODE), json_encode($got, JSON_UNESCAPED_UNICODE));
}

$ids = fn(array $rows) => array_column($rows, 'id');

// ── Qui est actif ───────────────────────────────────────────────────────────
check('is_active respecté', $ids(StaffService::activeOnly([
    ['id' => 1, 'is_active' => true],
    ['id' => 2, 'is_active' => false],
])), [1]);

check('« 0 » et « 1 » en chaînes', $ids(StaffService::activeOnly([
    ['id' => 1, 'is_active' => '1'],
    ['id' => 2, 'is_active' => '0'],
])), [1]);

check('active / enabled acceptés', $ids(StaffService::activeOnly([
    ['id' => 1, 'active'  => true],
    ['id' => 2, 'enabled' => false],
])), [1]);

check('deleted_at écarte', $ids(StaffService::activeOnly([
    ['id' => 1],
    ['id' => 2, 'deleted_at' => '2026-01-04'],
    ['id' => 3, 'archived_at' => '2026-01-04'],
])), [1]);

check('status texte', $ids(StaffService::activeOnly([
    ['id' => 1, 'status' => 'ACTIVE'],
    ['id' => 2, 'status' => 'inactive'],
    ['id' => 3, 'status' => 'Archived'],
])), [1]);

// Le point le plus important : sans indicateur, on GARDE. Écarter sur une
// absence d'information viderait la liste le jour où le champ change de nom.
check('sans indicateur → gardé', $ids(StaffService::activeOnly([
    ['id' => 1],
    ['id' => 2, 'name' => 'Nathan'],
])), [1, 2]);

check('champ vide → gardé', $ids(StaffService::activeOnly([
    ['id' => 1, 'is_active' => ''],
    ['id' => 2, 'is_active' => null],
])), [1, 2]);

check('liste vide', StaffService::activeOnly([]), []);

// ── Qui est au planning ─────────────────────────────────────────────────────
$J = '2026-08-06';

check('employee_id', StaffService::scheduledIds([
    ['employee_id' => 11], ['employee_id' => 12],
], $J), ['11', '12']);

check('les autres noms de champ', StaffService::scheduledIds([
    ['franchisee_employee_id' => 21],
    ['id_employee' => 22],
    ['id_franchisee_employee' => 23],
    ['user_id' => 24],
], $J), ['21', '22', '23', '24']);

check('fiche imbriquée', StaffService::scheduledIds([
    ['employee' => ['id' => 31, 'name' => 'Nathan']],
], $J), ['31']);

// Nombre ou chaîne : la même personne, une seule fois.
check('nombre et chaîne dédoublonnés', StaffService::scheduledIds([
    ['employee_id' => 41], ['employee_id' => '41'],
], $J), ['41']);

check('lignes d\'un autre jour écartées', StaffService::scheduledIds([
    ['employee_id' => 51, 'date' => $J],
    ['employee_id' => 52, 'date' => '2026-08-05'],
], $J), ['51']);

check('date horodatée acceptée', StaffService::scheduledIds([
    ['employee_id' => 61, 'date' => $J . ' 06:00:00'],
], $J), ['61']);

check('autres noms de date', StaffService::scheduledIds([
    ['employee_id' => 71, 'work_date' => $J],
    ['employee_id' => 72, 'day' => '2026-08-05'],
    ['employee_id' => 73, 'scheduled_for_date' => $J],
], $J), ['71', '73']);

// Une ligne sans date n'est pas suspecte : l'endpoint filtre déjà.
check('ligne sans date gardée', StaffService::scheduledIds([
    ['employee_id' => 81],
], $J), ['81']);

check('lignes illisibles ignorées', StaffService::scheduledIds([
    'bruit',
    ['rien' => 1],
    ['employee_id' => 91],
], $J), ['91']);

check('planning vide', StaffService::scheduledIds([], $J), []);

// ── Ce que l'écran en fait ──────────────────────────────────────────────────
// La classe est instanciable sans dépôt tant qu'on n'appelle pas getEmployees().
$s = new StaffService(new class extends \App\Kitchen\app\Repositories\Staff\StaffRepository {
    public function __construct() {}
});

$equipe = [
    ['id' => 1, 'name' => 'A', 'initials' => 'A', 'on_schedule' => true],
    ['id' => 2, 'name' => 'B', 'initials' => 'B', 'on_schedule' => false],
];
check('planning connu → filtré',   $ids($s->roster($equipe)['list']), [1]);
check('planning connu → mode',     $s->roster($equipe)['mode'], 'scheduled');
check('planning connu',            $s->scheduleKnown($equipe), true);

$inconnu = [
    ['id' => 1, 'name' => 'A', 'initials' => 'A', 'on_schedule' => null],
    ['id' => 2, 'name' => 'B', 'initials' => 'B', 'on_schedule' => null],
];
// Toute l'équipe plutôt qu'une liste vide : une checklist inachevable est pire
// qu'une liste trop large, et l'écran écrit qu'il ne sait pas.
check('planning inconnu → tous',   $ids($s->roster($inconnu)['list']), [1, 2]);
check('planning inconnu → mode',   $s->roster($inconnu)['mode'], 'all_unknown');
check('planning inconnu',          $s->scheduleKnown($inconnu), false);

/* ── Le cas qui bloquait le magasin ──
   Planning SERVI mais ne designant personne : un planning pas encore saisi, ou
   saisi ailleurs. La liste se vidait, et plus aucune tache n'etait validable —
   equipe presente, magasin ouvert, ecran qui refuse de laisser signer. On rend
   toute l'equipe, et l'ecran dit pourquoi. */
$vide = [
    ['id' => 1, 'name' => 'A', 'initials' => 'A', 'on_schedule' => false],
    ['id' => 2, 'name' => 'B', 'initials' => 'B', 'on_schedule' => false],
];
check('planning vide → tous',      $ids($s->roster($vide)['list']), [1, 2]);
check('planning vide → mode',      $s->roster($vide)['mode'], 'all_empty');
check('planning vide, jamais []',  $s->roster($vide)['list'] === [], false);

check('aucune équipe → vide',      $s->roster([])['list'], []);
check('aucune équipe → mode',      $s->roster([])['mode'], 'none');
check('liste absente → vide',      $s->roster(null)['list'], []);
check('liste absente → mode',      $s->roster(null)['mode'], 'none');
check('liste absente → inconnu',   $s->scheduleKnown(null), false);

// onDuty reste la porte d'entrée de la cuisson : même verdict, liste seule.
check('onDuty suit roster',        $ids($s->onDuty($vide)), [1, 2]);

// ── Verdict ────────────────────────────────────────────────────────────────
if ($ko) {
    echo implode("\n", $ko) . "\n\n✗ " . count($ko) . " échec(s), $ok passées\n";
    exit(1);
}
echo "✓ $ok vérifications passées\n";
