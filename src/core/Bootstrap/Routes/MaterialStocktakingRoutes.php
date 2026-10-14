<?php

use App\Kitchen\app\Http\Controllers\Material\MaterialStocktakingController;
use FastRoute\RouteCollector;

return function (RouteCollector $r): void {
    $r->addRoute('GET', '/material-stocktaking', ['controller' => MaterialStocktakingController::class, 'method' => 'index']);
    $r->addRoute('POST', '/ajax/material-stocktaking/preview', ['controller' => MaterialStocktakingController::class, 'method' => 'preview']);
    $r->addRoute('POST', '/ajax/material-stocktaking/save', ['controller' => MaterialStocktakingController::class, 'method' => 'save']);
};
