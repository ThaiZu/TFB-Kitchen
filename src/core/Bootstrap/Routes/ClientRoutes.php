<?php


use FastRoute\RouteCollector;

return function (RouteCollector $r) {

    $r->addRoute('GET', '/clients', [
        'controller' => \App\Kitchen\app\Http\Controllers\Client\ClientController::class,
        'method' => 'index'
    ]);

    $r->addRoute('POST', '/ajax/clients', [
        'controller' => \App\Kitchen\app\Http\Controllers\Client\ClientController::class,
        'method' => 'ajaxInsert'
    ]);

    $r->addRoute('GET', '/clients/{clientId:\d+}/price-list', [
        'controller' => \App\Kitchen\app\Http\Controllers\Price\PriceController::class,
        'method' => 'priceList'
    ]);

    $r->addRoute('POST', '/ajax/clients/{clientId:\d+}/products/{productId:\d+}/prices', [
        'controller' => \App\Kitchen\app\Http\Controllers\Price\PriceController::class,
        'method' => 'ajaxInsertNewPrice'
    ]);

    $r->addRoute('DELETE', '/ajax/clients/{clientId:\d+}/products/{productId:\d+}/prices/{priceId:\d+}', [
        'controller' => \App\Kitchen\app\Http\Controllers\Price\PriceController::class,
        'method' => 'ajaxDelete'
    ]);

};
