<?php


use FastRoute\RouteCollector;

return function (RouteCollector $r) {

    $r->addRoute('GET', '/clients', [
        'controller' => \App\Supplier\app\Http\Controllers\Client\ClientController::class,
        'method' => 'index'
    ]);

    $r->addRoute('GET', '/clients/{clientId:\d+}/price-list', [
        'controller' => \App\Supplier\app\Http\Controllers\Price\PriceController::class,
        'method' => 'priceList'
    ]);

    $r->addRoute('POST', '/ajax/clients/{clientId:\d+}/products/{productId:\d+}/prices', [
        'controller' => \App\Supplier\app\Http\Controllers\Price\PriceController::class,
        'method' => 'ajaxInsertNewPrice'
    ]);

    $r->addRoute('DELETE', '/ajax/clients/{clientId:\d+}/products/{productId:\d+}/prices/{priceId:\d+}', [
        'controller' => \App\Supplier\app\Http\Controllers\Price\PriceController::class,
        'method' => 'ajaxDelete'
    ]);

};