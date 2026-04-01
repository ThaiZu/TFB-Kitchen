<?php


use FastRoute\RouteCollector;

return function(RouteCollector $r) {

    $r->addRoute('GET', '/dashboard', [
        'controller' => \App\Kitchen\app\Http\Controllers\Dashboard\DashboardController::class,
        'method'     => 'index'
    ]);

};