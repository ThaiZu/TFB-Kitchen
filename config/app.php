<?php



define('ROOT',
    (((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'))
        ? 'https://'
        : 'http://') . $_SERVER['SERVER_NAME'] . '/kitchen');

// L'API est hébergée séparément (autre hôte que le front). Sans cette
// surcharge, l'app interroge « http://<hôte courant>/api/v1 » : sur le serveur
// de test, ce chemin n'existe pas, donc /public/shops ne renvoie rien et le
// sélecteur de magasin du login reste vide.
// À surcharger par l'env KITCHEN_API_BASE (SetEnv dans .htaccess), sinon on
// retombe sur le même origine + /api/v1. Même mécanisme que pwa_consultant
// (CONSULTANT_API_BASE), qui fonctionne déjà sur ce serveur.
$__kitchenApiBase = $_SERVER['KITCHEN_API_BASE'] ?? $_ENV['KITCHEN_API_BASE'] ?? getenv('KITCHEN_API_BASE') ?: '';
define('API_BASE_URL',
    $__kitchenApiBase !== ''
        ? rtrim($__kitchenApiBase, '/')
        : ((((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'))
            ? 'https://'
            : 'http://') . $_SERVER['SERVER_NAME'] . '/api/v1'));

define('SHARED_FILES_URL',
    (((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'))
        ? 'https://'
        : 'http://') . $_SERVER['SERVER_NAME'] . '/shared-assets');

define('THEME_CONFIG_PATH',
    (((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'))
        ? 'https://'
        : 'http://') . $_SERVER['SERVER_NAME'] . '/shared/admin-theme-config.json');

define('JWT_SECRET_KEY', $_ENV['JWT_SECRET']); // Secret key for JWT
define('JWT_ISSUER', $_ENV['JWT_ISSUER']); // Issuer
define('JWT_ACCESS_TOKEN_EXPIRY', $_ENV['JWT_ACCESS_TOKEN_EXPIRY']); // Access token expiry in seconds
define('JWT_REFRESH_TOKEN_EXPIRY', $_ENV['JWT_REFRESH_TOKEN_EXPIRY']); // Refresh token expiry in seconds

define('DEFAULT_LANGUAGE', $_ENV['DEFAULT_LANGUAGE']);
define('COUNTRY_CODE', $_ENV['DEFAULT_COUNTRY']);
define('CURRENCY', $_ENV['CURRENCY']);
if (!defined('CURRENCY_SYMBOL')) {
    define('CURRENCY_SYMBOL', $_ENV['CURRENCY_SYMBOL']);
}
define('APP_CURRENCY_SYMBOL', $_ENV['CURRENCY_SYMBOL']);
define('APP_NAME', $_ENV['APP_NAME']);
define('APP_DESC', $_ENV['APP_DESC']);


const DEBUG = true;
