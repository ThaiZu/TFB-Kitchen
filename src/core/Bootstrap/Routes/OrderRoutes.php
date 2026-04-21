<?php

use FastRoute\RouteCollector;

return function (RouteCollector $r) {

    // Lista zamówień (z filtrami: data, nazwa klienta, pending_only)
    $r->addRoute('GET', '/orders', [
        'controller' => \App\Kitchen\app\Http\Controllers\Order\OrderController::class,
        'method'     => 'index',
    ]);

    // Szczegóły zamówienia
    $r->addRoute('GET', '/orders/{id:\d+}', [
        'controller' => \App\Kitchen\app\Http\Controllers\Order\OrderController::class,
        'method'     => 'show',
    ]);
};

