<?php



define('ROOT',
    (((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'))
        ? 'https://'
        : 'http://') . $_SERVER['SERVER_NAME'] . '/kitchen');

define('API_BASE_URL',
    (((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'))
        ? 'https://'
        : 'http://') . $_SERVER['SERVER_NAME'] . '/api/v1');

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
