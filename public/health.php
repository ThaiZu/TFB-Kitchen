
<?php

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
try {
    require_once __DIR__ . '/../vendor/autoload.php';

    if (!class_exists(\DI\Container::class)) {
        throw new RuntimeException('DI unavailable');
    }

    if (!class_exists(\Twig\Environment::class)) {
        throw new RuntimeException('Twig unavailable');
    }

    http_response_code(204);
} catch (Throwable $e) {
    http_response_code(503);
    echo 'UNHEALTHY';
}
