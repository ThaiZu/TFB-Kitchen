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
// Depuis la révision du 2 août 2026 : plus de connexion personnelle, plus d'id
// de boutique à saisir. Deux réglages, une URL et un jeton d'appareil — et
// c'est le jeton qui porte la boutique.
$url = 'https://exemple.tld/webshop/backoffice_franchisee/?shop=2';
$tok = str_repeat('a1b2', 16);   // 64 caractères, comme les vrais

check('URL + jeton',                   $m->webshopUrl($url, $tok), $url);
check('tout est là : aucune raison',   $m->webshopBlocker($url, $tok), null);
check('sans URL',                      $m->webshopUrl('', $tok), null);
check('sans URL : raison',             $m->webshopBlocker('', $tok), 'no_url');
check('sans jeton',                    $m->webshopUrl($url, ''), null);
check('sans jeton : raison',           $m->webshopBlocker($url, ''), 'no_token');
check('sans jeton : null aussi',       $m->webshopBlocker($url, null), 'no_token');

// L'URL est prise telle quelle : c'est le jeton qui impose la boutique, et le
// serveur ignore ?shop=. Y ajouter quoi que ce soit laisserait croire que la
// tablette choisit son magasin.
check("l'URL n'est pas complétée",     $m->webshopUrl('https://x.tld/bo', $tok), 'https://x.tld/bo');
check('espaces rognés',                $m->webshopUrl('  https://x.tld/bo  ', $tok), 'https://x.tld/bo');

// Le champ finit en src d'une iframe : tout ce qui n'est pas http(s) est
// refusé, y compris ce qui ressemble à une URL.
check('javascript: refusé',            $m->webshopBlocker('javascript:alert(1)', $tok), 'bad_url');
check('data: refusé',                  $m->webshopBlocker('data:text/html,<b>x', $tok), 'bad_url');
check('sans schéma refusé',            $m->webshopBlocker('exemple.tld/bo', $tok), 'bad_url');
check('http accepté',                  $m->webshopBlocker('http://exemple.tld/bo', $tok), null);
check('majuscules du schéma',          $m->webshopBlocker('HTTPS://exemple.tld/bo', $tok), null);

// La faute qu'on attrape sur le jeton, c'est le mauvais champ ou le
// copier-coller approximatif — pas le mauvais jeton, que seul le serveur sait.
check('URL collée dans le jeton',      $m->webshopBlocker($url, 'https://exemple.tld/bo'), 'bad_token');
check('jeton trop court',              $m->webshopBlocker($url, 'abc123'), 'bad_token');
check('espace au milieu',              $m->webshopBlocker($url, str_repeat('ab', 8) . ' x'), 'bad_token');
check('espaces autour : tolérés',      $m->webshopBlocker($url, '  ' . $tok . '  '), null);
check('jeton majuscules',              $m->webshopBlocker($url, strtoupper($tok)), null);

// ── La base d'API, déduite de l'URL ───────────────────────────────────────
// Un seul champ pour une seule information : deux champs à saisir seraient
// deux occasions de les rendre incohérents.
check('api déduite',                   $m->webshopApiBase($url), 'https://exemple.tld/webshop/api');
check('api : le port suit',            $m->webshopApiBase('http://127.0.0.1:8080/webshop/backoffice_franchisee/'),
    'http://127.0.0.1:8080/webshop/api');
check('api : le chemin saute',         $m->webshopApiBase('https://x.tld/a/b/c?d=e'), 'https://x.tld/webshop/api');
check('api sans URL',                  $m->webshopApiBase(''), null);
check('api sur URL malformée',         $m->webshopApiBase('exemple.tld'), null);

// ── Le jeton ne s'affiche pas ─────────────────────────────────────────────
// Une tablette de comptoir reste allumée devant tout le monde : la page dit
// lequel est en place, jamais lequel c'est.
check('indice : quatre caractères',    $m->tokenHint('0123456789abcdef'), '…cdef');
check('indice : rien sans jeton',      $m->tokenHint(''), null);
check('indice : rien sur null',        $m->tokenHint(null), null);

// ── Verdict ───────────────────────────────────────────────────────────────
echo $ko === []
    ? "✓ {$ok} vérifications passées\n"
    : sprintf("%d passées, %d échouées :\n%s\n", $ok, count($ko), implode("\n", $ko));
exit($ko === [] ? 0 : 1);
