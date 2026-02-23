<?php

use App\Supplier\app\Services\Auth\JwtService;
use App\Supplier\core\Bootstrap\App;
use App\Supplier\core\Support\GlobalRegistry;
use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

// Załaduj .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../', null, false);
$dotenv->load();

require __DIR__ . '/../config/app.php';
require __DIR__ . '/../src/core/Support/functions.php';

DEBUG ? ini_set('display_errors', 1) : ini_set('display_errors', 0);


GlobalRegistry::set('lang_code', getUserLanguage());

$container = require __DIR__ . '/../src/core/Container/Container.php';

$app = $container->get(App::class);
$app->loadController();