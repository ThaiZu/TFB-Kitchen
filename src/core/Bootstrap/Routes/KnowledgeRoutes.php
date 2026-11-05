<?php


use FastRoute\RouteCollector;

return function(RouteCollector $r) {

    // Keep the singular URL used in older bookmarks as an alias.
    $r->addRoute('GET', '/knowledge/product/{id:\\d+}', [
        'controller' => \App\Kitchen\app\Http\Controllers\Knowledge\Product\TechnicalSheetController::class,
        'method' => 'show'
    ]);

    // VIEW
    $r->addRoute('GET', '/knowledge/products/{id:\d+}', [
        'controller' => \App\Kitchen\app\Http\Controllers\Knowledge\Product\TechnicalSheetController::class,
        'method'     => 'show'
    ]);


    // VIEW
    $r->addRoute('GET', '/knowledge', [
        'controller' => \App\Kitchen\app\Http\Controllers\Knowledge\KnowledgeController::class,
        'method'     => 'index'
    ]);

    $r->addRoute('GET', '/knowledge/products', [
        'controller' => \App\Kitchen\app\Http\Controllers\Knowledge\Product\ProductController::class,
        'method'     => 'index'
    ]);


};