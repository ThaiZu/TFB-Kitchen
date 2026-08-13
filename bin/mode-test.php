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
// « objectives » a quitté les deux menus qui le portaient : l'entrée était
// disabled avec href="#", elle n'ouvrait rien. Ce test le fige, pour qu'on ne
// la réintroduise pas sans écran derrière.
check('production : menu',             $m->navKeys('production'),
    ['dashboard', 'production', 'checklists', 'orders', 'knowledge', 'complaints']);
check('aucun mode ne propose objectives',
    $m->allows('production', 'objectives') || $m->allows('gestion', 'objectives'), false);
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

// ── La configuration servie par pwa_kitchen_param ───────────────────────────
// Ces regles decident de ce que la tablette affiche. La plus importante n'est
// pas qu'une configuration valide passe — c'est qu'une configuration FAUSSE ne
// vide jamais un menu. Une tablette sans menu ne se repare pas au doigt.

$def = DeviceModeService::DEFAULT_NAV;

// Rien de servi : les defauts, a l'identique.
check('config nulle → défauts',        DeviceModeService::sanitise(null)['nav'], $def);
check('config vide → défauts',         DeviceModeService::sanitise([])['nav'], $def);
check('« modes » absent → défauts',    DeviceModeService::sanitise(['autre' => 1])['nav'], $def);
check('« modes » non tableau',         DeviceModeService::sanitise(['modes' => 'x'])['nav'], $def);

// Une case decochee : l'entree disparait, les autres modes ne bougent pas.
$sans = DeviceModeService::sanitise(['modes' => [
    'production' => ['nav' => ['dashboard', 'production', 'checklists', 'orders', 'knowledge']],
]]);
check('réclamations retirées',         $sans['nav']['production'],
    ['dashboard', 'production', 'checklists', 'orders', 'knowledge']);
check('gestion intacte',               $sans['nav']['gestion'],   $def['gestion']);
check('webshop intact',                $sans['nav']['webshop'],   $def['webshop']);
// Les onglets ne sont pas touches par une config qui ne parle que du menu.
check('onglets non touchés',           $sans['tabs']['production'], DeviceModeService::DEFAULT_TABS['production']);

// L'ordre servi est l'ordre affiche : c'est sort_order qui le porte.
check('ordre respecté',                DeviceModeService::sanitise(['modes' => [
    'gestion' => ['nav' => ['complaints', 'dashboard', 'knowledge']],
]])['nav']['gestion'], ['complaints', 'dashboard', 'knowledge']);

// ── Ce qu'on refuse ────────────────────────────────────────────────────────
// Une fonctionnalite que l'application ne sait pas rendre ajouterait une entree
// de menu qui n'ouvre rien : ignoree.
check('feature inconnue ignorée',      DeviceModeService::sanitise(['modes' => [
    'gestion' => ['nav' => ['dashboard', 'facturation', 'checklists']],
]])['nav']['gestion'], ['dashboard', 'checklists']);

check('doublons écartés',              DeviceModeService::sanitise(['modes' => [
    'gestion' => ['nav' => ['dashboard', 'dashboard', 'checklists']],
]])['nav']['gestion'], ['dashboard', 'checklists']);

check('casse et espaces',              DeviceModeService::sanitise(['modes' => [
    'GESTION' => ['nav' => [' Dashboard ', 'CHECKLISTS']],
]])['nav']['gestion'], ['dashboard', 'checklists']);

check('mode inconnu ignoré',           DeviceModeService::sanitise(['modes' => [
    'comptoir' => ['nav' => ['dashboard']],
]])['nav'], $def);

check('valeurs non textuelles',        DeviceModeService::sanitise(['modes' => [
    'gestion' => ['nav' => [['x'], null, true, 'dashboard']],
]])['nav']['gestion'], ['dashboard']);

// LA garde : un menu vide garde le defaut du mode. Une ligne mal saisie en base
// ne doit pas immobiliser un magasin.
check('nav vide → défaut gardé',       DeviceModeService::sanitise(['modes' => [
    'production' => ['nav' => []],
]])['nav']['production'], $def['production']);

check('nav toute inconnue → défaut',   DeviceModeService::sanitise(['modes' => [
    'production' => ['nav' => ['facturation', 'rh']],
]])['nav']['production'], $def['production']);

check('tabs vides → défaut gardé',     DeviceModeService::sanitise(['modes' => [
    'production' => ['tabs' => []],
]])['tabs']['production'], DeviceModeService::DEFAULT_TABS['production']);

// La barre du bas tient quatre onglets : au-dela, les suivants disparaitraient
// sans un mot. On tronque, visiblement, plutot que de laisser faire.
check('cinq onglets → quatre',         DeviceModeService::sanitise(['modes' => [
    'production' => ['tabs' => ['dashboard', 'production', 'checklists', 'orders', 'knowledge']],
]])['tabs']['production'], ['dashboard', 'production', 'checklists', 'orders']);

// Les vues internes du WebShop n'existent qu'en bas.
check('ws_* accepté en onglet',        DeviceModeService::sanitise(['modes' => [
    'webshop' => ['tabs' => ['ws_stock', 'ws_prep']],
]])['tabs']['webshop'], ['ws_stock', 'ws_prep']);
check('ws_* refusé au menu',           DeviceModeService::sanitise(['modes' => [
    'webshop' => ['nav' => ['ws_stock']],
]])['nav']['webshop'], $def['webshop']);

// ── Et une fois appliquée ──────────────────────────────────────────────────
$m2 = new DeviceModeService();
$m2->applyConfig(['modes' => ['production' => [
    'nav'  => ['dashboard', 'checklists'],
    'tabs' => ['dashboard', 'checklists'],
]]]);
check('appliquée : menu',              $m2->navKeys('production'), ['dashboard', 'checklists']);
check('appliquée : onglets',           $m2->tabKeys('production'), ['dashboard', 'checklists']);
check('appliquée : allows suit',       $m2->allows('production', 'orders'), false);
check('appliquée : gestion intacte',   $m2->navKeys('gestion'), $def['gestion']);

$m3 = new DeviceModeService();
$m3->applyConfig(null);
check('config nulle : rien ne bouge',  $m3->navKeys('production'), $def['production']);

// ── Verdict ───────────────────────────────────────────────────────────────
echo $ko === []
    ? "✓ {$ok} vérifications passées\n"
    : sprintf("%d passées, %d échouées :\n%s\n", $ok, count($ko), implode("\n", $ko));
exit($ko === [] ? 0 : 1);
