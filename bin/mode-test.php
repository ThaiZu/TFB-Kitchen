<?php
/**
 * Vérifie DeviceModeService sans serveur, sans cookie, sans navigateur.
 *
 *     php bin/mode-test.php
 *
 * Le mode décide de ce que la tablette affiche : une règle fausse ici ne se
 * voit pas à l'écran, elle se voit par une section manquante que personne ne
 * pense à réclamer. Les trois choses à tenir sont le défaut (production, pour
 * ne rien changer aux tablettes en service), la robustesse aux valeurs
 * inconnues (jamais de menu vide), et le refus d'ouvrir un WebShop
 * partiellement configuré.
 */
require __DIR__ . '/../vendor/autoload.php';

use App\Kitchen\app\Services\Me\DeviceModeService;

$ok = 0;
$ko = [];
function check(string $what, $got, $want): void
{
    global $ok, $ko;
    if ($got === $want) { $ok++; return; }
    $ko[] = sprintf("  ✗ %s\n      attendu : %s\n      obtenu  : %s",
        $what, json_encode($want, JSON_UNESCAPED_UNICODE), json_encode($got, JSON_UNESCAPED_UNICODE));
}

$m = new DeviceModeService();

// ── Le défaut ─────────────────────────────────────────────────────────────
// C'est la garantie du brief : une tablette déjà en service ne change pas de
// comportement au déploiement.
check('trois modes',                   $m->modes(), ['gestion', 'production', 'webshop']);
check('défaut = production',           DeviceModeService::DEFAULT_MODE, 'production');
check('absent → production',           $m->normalise(null), 'production');
check('vide → production',             $m->normalise(''), 'production');
check('inconnu → production',          $m->normalise('vente'), 'production');
check('casse ignorée',                 $m->normalise('WebShop'), 'webshop');
check('espaces ignorés',               $m->normalise('  gestion '), 'gestion');

// ── Ce que chaque mode montre ─────────────────────────────────────────────
check('production : menu inchangé',    $m->navKeys('production'),
    ['dashboard', 'production', 'objectives', 'checklists', 'orders', 'knowledge', 'complaints']);
check('production : onglets inchangés', $m->tabKeys('production'),
    ['dashboard', 'production', 'checklists', 'orders']);

check('gestion : pas de production',   $m->allows('gestion', 'production'), false);
check('gestion : pas de commandes',    $m->allows('gestion', 'orders'), false);
check('gestion : checklists',          $m->allows('gestion', 'checklists'), true);
check('gestion : connaissances',       $m->allows('gestion', 'knowledge'), true);
check('gestion : réclamations',        $m->allows('gestion', 'complaints'), true);
check('gestion : tableau de bord',     $m->allows('gestion', 'dashboard'), true);
check('gestion : pas de webshop',      $m->allows('gestion', 'webshop'), false);

check('webshop : le webshop seul',     $m->navKeys('webshop'), ['webshop']);
check('webshop : pas de production',   $m->allows('webshop', 'production'), false);
check('webshop : pas de dashboard',    $m->allows('webshop', 'dashboard'), false);

// Un mode forgé ne doit jamais vider la navigation : la tablette serait
// irrécupérable au doigt.
check('mode forgé → menu du défaut',   $m->navKeys('n/importe quoi'), $m->navKeys('production'));
check('mode forgé → onglets du défaut', $m->tabKeys(null), $m->tabKeys('production'));

// ── Où l'on atterrit ──────────────────────────────────────────────────────
check('accueil production',            $m->home('production'), '/dashboard');
check('accueil gestion',               $m->home('gestion'), '/dashboard');
check('accueil webshop',               $m->home('webshop'), '/webshop');
check('accueil mode inconnu',          $m->home('zzz'), '/dashboard');

// ── WebShop : ouvrir, ou dire pourquoi on n'ouvre pas ─────────────────────
$url = 'https://exemple.tld/webshop/backoffice_franchisee/';

check('URL + boutique',                $m->webshopUrl($url, 2),
    'https://exemple.tld/webshop/backoffice_franchisee?shop=2');
check('sans URL',                      $m->webshopUrl('', 2), null);
check('sans URL : raison',             $m->webshopBlocker('', 2), 'no_url');
check('sans boutique',                 $m->webshopUrl($url, 0), null);
check('sans boutique : raison',        $m->webshopBlocker($url, 0), 'no_shop');
check('boutique négative',             $m->webshopBlocker($url, -3), 'no_shop');
check('tout est là : aucune raison',   $m->webshopBlocker($url, 7), null);

// Le champ est saisi à la main sur la tablette et finit en src d'une iframe :
// tout ce qui n'est pas http(s) est refusé, y compris ce qui ressemble à une
// URL.
check('javascript: refusé',            $m->webshopBlocker('javascript:alert(1)', 2), 'bad_url');
check('data: refusé',                  $m->webshopBlocker('data:text/html,<b>x', 2), 'bad_url');
check('sans schéma refusé',            $m->webshopBlocker('exemple.tld/bo', 2), 'bad_url');
check('http accepté',                  $m->webshopBlocker('http://exemple.tld/bo', 2), null);
check('majuscules du schéma',          $m->webshopBlocker('HTTPS://exemple.tld/bo', 2), null);

// Une base qui porte déjà une query garde la sienne : on ajoute la boutique,
// on ne la remplace pas.
check('query existante conservée',     $m->webshopUrl('https://x.tld/bo?lang=fr', 4),
    'https://x.tld/bo?lang=fr&shop=4');
// Un id de boutique venant d'un champ texte arrive en chaîne.
check('boutique en chaîne',            $m->webshopUrl($url, '9'),
    'https://exemple.tld/webshop/backoffice_franchisee?shop=9');

// ── Verdict ───────────────────────────────────────────────────────────────
echo $ko === []
    ? "✓ {$ok} vérifications passées\n"
    : sprintf("%d passées, %d échouées :\n%s\n", $ok, count($ko), implode("\n", $ko));
exit($ko === [] ? 0 : 1);
