<?php


use App\Kitchen\app\Http\Controllers\Auth\AuthController;
use FastRoute\RouteCollector;

return function(RouteCollector $r) {
    $r->addRoute('GET', '/ajax/session', [
        'controller' => \App\Kitchen\app\Http\Controllers\Auth\SessionController::class,
        'method' => 'status',
    ]);

    $r->addRoute('GET', '/auth', [
        'controller' => AuthController::class,
        'method'     => 'index'
    ]);

    $r->addRoute('POST', '/auth', [
        'controller' => AuthController::class,
        'method'     => 'index'
    ]);

    $r->addRoute('GET', '/logout', [
        'controller' => AuthController::class,
        'method'     => 'logout'
    ]);


};