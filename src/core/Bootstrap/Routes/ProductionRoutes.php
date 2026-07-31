<?php

use FastRoute\RouteCollector;

return function (RouteCollector $r) {

    // Plan de production du jour
    $r->addRoute('GET', '/production', [
        'controller' => \App\Kitchen\app\Http\Controllers\Production\ProductionController::class,
        'method'     => 'index',
    ]);

    // Avancement d'une ligne (statut et/ou quantité produite)
    $r->addRoute('PATCH', '/ajax/production/{id:\d+}', [
        'controller' => \App\Kitchen\app\Http\Controllers\Production\ProductionController::class,
        'method'     => 'ajaxUpdate',
    ]);
};
