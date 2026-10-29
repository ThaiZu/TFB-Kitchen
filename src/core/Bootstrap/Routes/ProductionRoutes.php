<?php

use FastRoute\RouteCollector;

return function (RouteCollector $r) {

    $controller = \App\Kitchen\app\Http\Controllers\Production\ProductionController::class;
    $todayController = \App\Kitchen\app\Http\Controllers\Production\TodayProductionController::class;
    $wasteController = \App\Kitchen\app\Http\Controllers\Production\ProductionWasteController::class;
    $stockAdjustmentController = \App\Kitchen\app\Http\Controllers\Production\ProductionStockAdjustmentController::class;

    $dayController = \App\Kitchen\app\Http\Controllers\Production\ProductionDayController::class;
    $r->addRoute('GET','/ajax/production-sector',['controller'=>$dayController,'method'=>'sectors']);
    $r->addRoute('POST','/ajax/production-sector',['controller'=>$dayController,'method'=>'sector']);
    $r->addRoute('GET','/production-day',['controller'=>$dayController,'method'=>'index']);
    $r->addRoute('GET','/production-day/data',['controller'=>$dayController,'method'=>'data']);
    $r->addRoute('POST','/production-day/photos',['controller'=>$dayController,'method'=>'uploadPhoto']);
    $r->addRoute('POST','/production-day/{operation:open|adjust|close|prepare}',['controller'=>$dayController,'method'=>'command']);

    // Compact execution list backed by the canonical Panel production plan.
    $r->addRoute('GET', '/production/today', ['controller'=>\App\Kitchen\app\Http\Controllers\Production\ProductionDayController::class,'method'=>'legacy',]);
    $r->addRoute('POST', '/ajax/production/today/cycles/confirm', [
        'controller' => $todayController,
        'method'     => 'confirmCycle',
    ]);
    $r->addRoute('POST', '/ajax/production/today/cycles/target', [
        'controller' => $todayController,
        'method'     => 'adjustCycleTarget',
    ]);

    // A single, tablet-friendly waste declaration. It delegates to the same
    // atomic inventory contract as the detailed inventory screen.
    $r->addRoute('GET', '/production/waste', ['controller'=>\App\Kitchen\app\Http\Controllers\Production\ProductionDayController::class,'method'=>'legacy',]);
    $r->addRoute('POST', '/ajax/production/waste', [
        'controller' => $wasteController,
        'method'     => 'create',
    ]);
    $r->addRoute('GET', '/production/stock-correction', ['controller'=>\App\Kitchen\app\Http\Controllers\Production\ProductionDayController::class,'method'=>'legacy',]);
    $r->addRoute('POST', '/ajax/production/stock-correction', [
        'controller' => $stockAdjustmentController,
        'method'     => 'add',
    ]);

    // Écran unique, quatre vues : matin, midi, après-midi, stock.
    $r->addRoute('GET', '/production', ['controller'=>\App\Kitchen\app\Http\Controllers\Production\ProductionDayController::class,'method'=>'legacy',]);

    // Stock live + propositions de recuisson, dans la même réponse.
    $r->addRoute('GET', '/ajax/production/stock', [
        'controller' => $controller,
        'method'     => 'ajaxStock',
    ]);

    // Mise en rayon : c'est elle qui rend les produits vendables.
    $r->addRoute('POST', '/ajax/production/shelf', [
        'controller' => $controller,
        'method'     => 'ajaxShelve',
    ]);

    // Validation de la MEP du jour : constate ce qui a été produit à l'atelier.
    $r->addRoute('POST', '/ajax/production/mep/validate', [
        'controller' => $controller,
        'method'     => 'ajaxValidateMep',
    ]);

    // Encodage de la MEP du lendemain, l'après-midi.
    $r->addRoute('POST', '/ajax/production/mep', [
        'controller' => $controller,
        'method'     => 'ajaxSaveMep',
    ]);

    // Validation d'une recuisson.
    $r->addRoute('POST', '/ajax/production/rebake', [
        'controller' => $controller,
        'method'     => 'ajaxRebake',
    ]);
};
