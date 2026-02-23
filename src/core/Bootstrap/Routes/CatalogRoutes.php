<?php


use FastRoute\RouteCollector;

return function(RouteCollector $r) {

    // VIEW
    $r->addRoute('GET', '/catalog', [
        'controller' => \App\Supplier\app\Http\Controllers\Catalog\CatalogController::class,
        'method'     => 'index'
    ]);



    // AJAX: products
    $r->addRoute('GET', '/ajax/catalog/products', [
        'controller' => \App\Supplier\app\Http\Controllers\Catalog\CatalogProductController::class,
        'method'     => 'ajaxGetAll'
    ]);

    $r->addRoute('POST', '/ajax/catalog/products', [
        'controller' => \App\Supplier\app\Http\Controllers\Catalog\CatalogProductController::class,
        'method'     => 'ajaxInsert'
    ]);

    $r->addRoute('PUT', '/ajax/catalog/products/{id:\d+}', [
        'controller' => \App\Supplier\app\Http\Controllers\Catalog\CatalogProductController::class,
        'method'     => 'ajaxUpdate'
    ]);

    $r->addRoute('DELETE', '/ajax/catalog/products/{id:\d+}', [
        'controller' => \App\Supplier\app\Http\Controllers\Catalog\CatalogProductController::class,
        'method'     => 'ajaxDelete'
    ]);



    $r->addRoute('POST', '/ajax/catalog/products/{productId:\d+}/allergens', [
        'controller' => \App\Supplier\app\Http\Controllers\Catalog\CatalogProductController::class,
        'method'     => 'ajaxAssignAllergen'
    ]);

    $r->addRoute('DELETE', '/ajax/catalog/products/{productId:\d+}/allergens/{allergenId:\d+}', [
        'controller' => \App\Supplier\app\Http\Controllers\Catalog\CatalogProductController::class,
        'method'     => 'ajaxUnassignAllergen'
    ]);
};