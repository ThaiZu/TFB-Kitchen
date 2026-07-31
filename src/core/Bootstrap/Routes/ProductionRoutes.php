<?php

use FastRoute\RouteCollector;

return function (RouteCollector $r) {

    $controller = \App\Kitchen\app\Http\Controllers\Production\ProductionController::class;

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

    // Validation de la MEP du jour : c'est elle qui rend les produits vendables.
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
