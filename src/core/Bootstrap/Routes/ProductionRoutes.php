<?php

use FastRoute\RouteCollector;

return function (RouteCollector $r) {

    $controller = \App\Kitchen\app\Http\Controllers\Production\ProductionController::class;
    $todayController = \App\Kitchen\app\Http\Controllers\Production\TodayProductionController::class;
    $wasteController = \App\Kitchen\app\Http\Controllers\Production\ProductionWasteController::class;
    $stockAdjustmentController = \App\Kitchen\app\Http\Controllers\Production\ProductionStockAdjustmentController::class;

    // Compact execution list backed by the canonical Panel production plan.
    $r->addRoute('GET', '/production/today', [
        'controller' => $todayController,
        'method'     => 'index',
    ]);
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
    $r->addRoute('GET', '/production/waste', [
        'controller' => $wasteController,
        'method'     => 'index',
    ]);
    $r->addRoute('POST', '/ajax/production/waste', [
        'controller' => $wasteController,
        'method'     => 'create',
    ]);
    $r->addRoute('GET', '/production/stock-correction', [
        'controller' => $stockAdjustmentController,
        'method'     => 'index',
    ]);
    $r->addRoute('POST', '/ajax/production/stock-correction', [
        'controller' => $stockAdjustmentController,
        'method'     => 'add',
    ]);

    // Écran unique, quatre vues : matin, midi, après-midi, stock.
    $r->addRoute('GET', '/production', [
        'controller' => $controller,
        'method'     => 'index',
    ]);

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
