<?php
/**
 * Vérifie ChecklistFocusService sans serveur, sans horloge, sans navigateur.
 *
 *     php bin/checklist-test.php
 *
 * Cette règle décide ce que l'équipe voit en ouvrant l'écran le matin. Ce qui
 * doit tenir ici n'est pas qu'elle trouve la bonne checklist dans le cas
 * évident — c'est qu'elle n'en propose JAMAIS une déjà terminée, et qu'elle
 * s'abstienne plutôt que de deviner. Une faute à cet endroit fait rouvrir un
 * travail fini, ou pire, fait croire qu'il reste à faire.
 */
require __DIR__ . '/../vendor/autoload.php';

use App\Kitchen\app\Services\Checklist\ChecklistFocusService;

$ok = 0;
$ko = [];
function check(string $what, $got, $want): void
{
    global $ok, $ko;
    if ($got === $want) { $ok++; return; }
    $ko[] = sprintf("  ✗ %s\n      attendu : %s\n      obtenu  : %s",
        $what, json_encode($want, JSON_UNESCAPED_UNICODE), json_encode($got, JSON_UNESCAPED_UNICODE));
}

$f = new ChecklistFocusService();

/** Une journée type : ouverture, contrôles, fermeture. */
function jour(int $ouv_done = 0, int $hac_done = 0, int $fer_done = 0): array
{
    return [
        ['id' => 11, 'name' => 'Ouverture',  'execution_time' => '06:00:00', 'tasks_done' => $ouv_done, 'tasks_total' => 5],
        ['id' => 12, 'name' => 'HACCP',      'execution_time' => '11:00:00', 'tasks_done' => $hac_done, 'tasks_total' => 3],
        ['id' => 13, 'name' => 'Fermeture',  'execution_time' => '19:00:00', 'tasks_done' => $fer_done, 'tasks_total' => 4],
    ];
}

// ── L'heure décide ──────────────────────────────────────────────────────────
check('06:10 → ouverture',              $f->pick(jour(), '06:10'), 11);
check('à l\'heure pile → ouverture',    $f->pick(jour(), '06:00'), 11);
check('11:30 → contrôles',              $f->pick(jour(), '11:30'), 12);
check('19:45 → fermeture',              $f->pick(jour(), '19:45'), 13);
check('23:59 → fermeture',              $f->pick(jour(), '23:59'), 13);

// Avant la première : on prépare celle qui vient, pas celle de la veille.
check('05:00 → la prochaine',           $f->pick(jour(), '05:00'), 11);

// ── Une checklist terminée n'est jamais proposée ────────────────────────────
check('ouverture faite → la suivante',  $f->pick(jour(5), '06:10'), 12);
check('les deux faites → fermeture',    $f->pick(jour(5, 3), '11:30'), 13);
check('tout fait → rien',               $f->pick(jour(5, 3, 4), '19:45'), null);
check('tout fait dès le matin → rien',  $f->pick(jour(5, 3, 4), '06:10'), null);

// Partiellement faite, ce n'est pas fini : on la rouvre.
check('2/5 à 06:10 → ouverture',        $f->pick(jour(2), '06:10'), 11);
check('4/5 à 11:30 → contrôles',        $f->pick(jour(4), '11:30'), 12);

// ── Ce qu'on refuse de deviner ──────────────────────────────────────────────
check('aucune checklist → rien',        $f->pick([], '08:00'), null);

// Sans heure planifiée nulle part, la première non terminée : c'est un défaut
// assumé, pas une déduction.
check('sans heure → la première',       $f->pick([
    ['id' => 21, 'execution_time' => null, 'tasks_done' => 0, 'tasks_total' => 2],
    ['id' => 22, 'execution_time' => '',   'tasks_done' => 0, 'tasks_total' => 2],
], '08:00'), 21);

check('sans heure, la 1re finie → la 2e', $f->pick([
    ['id' => 21, 'execution_time' => null, 'tasks_done' => 2, 'tasks_total' => 2],
    ['id' => 22, 'execution_time' => null, 'tasks_done' => 0, 'tasks_total' => 2],
], '08:00'), 22);

// Une checklist vide n'est pas « terminée », elle n'a rien à ouvrir.
check('checklist vide ignorée',         $f->pick([
    ['id' => 31, 'execution_time' => '06:00', 'tasks_done' => 0, 'tasks_total' => 0],
    ['id' => 32, 'execution_time' => '07:00', 'tasks_done' => 0, 'tasks_total' => 3],
], '09:00'), 32);

check('toutes vides → rien',            $f->pick([
    ['id' => 31, 'execution_time' => '06:00', 'tasks_done' => 0, 'tasks_total' => 0],
], '09:00'), null);

// ── Données douteuses : on ne plante pas, on ne choisit pas au hasard ───────
check('entrée non tableau ignorée',     $f->pick([
    'bruit',
    ['id' => 41, 'execution_time' => '06:00', 'tasks_done' => 0, 'tasks_total' => 2],
], '09:00'), 41);

check('id absent ignoré',               $f->pick([
    ['execution_time' => '06:00', 'tasks_done' => 0, 'tasks_total' => 2],
    ['id' => 42, 'execution_time' => '07:00', 'tasks_done' => 0, 'tasks_total' => 2],
], '09:00'), 42);

check('heure aberrante ignorée',        $f->pick([
    ['id' => 51, 'execution_time' => '99:99', 'tasks_done' => 0, 'tasks_total' => 2],
    ['id' => 52, 'execution_time' => '07:00', 'tasks_done' => 0, 'tasks_total' => 2],
], '09:00'), 52);

check('heure courante illisible → 1re', $f->pick(jour(), 'pas une heure'), 11);

check('« HH:MM » sans secondes',        $f->pick([
    ['id' => 61, 'execution_time' => '06:00', 'tasks_done' => 0, 'tasks_total' => 2],
    ['id' => 62, 'execution_time' => '14:00', 'tasks_done' => 0, 'tasks_total' => 2],
], '15:00'), 62);

// ── Verdict ────────────────────────────────────────────────────────────────
if ($ko) {
    echo implode("\n", $ko) . "\n\n✗ " . count($ko) . " échec(s), $ok passées\n";
    exit(1);
}
echo "✓ $ok vérifications passées\n";
