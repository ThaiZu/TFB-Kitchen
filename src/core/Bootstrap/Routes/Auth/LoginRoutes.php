<?php


use App\Supplier\app\Http\Controllers\Auth\AuthController;
use FastRoute\RouteCollector;

return function(RouteCollector $r) {

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