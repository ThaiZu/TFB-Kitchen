<?php


use FastRoute\RouteCollector;

return function (RouteCollector $r) {

    $r->addRoute('POST', '/ajax/ingredients', [
        'controller' => \App\Supplier\app\Http\Controllers\Ingredient\IngredientController::class,
        'method' => 'ajaxInsert'
    ]);

    $r->addRoute('GET', '/ajax/catalog/products/{productId:\d+}/ingredients', [
        'controller' => \App\Supplier\app\Http\Controllers\Ingredient\IngredientController::class,
        'method' => 'ajaxGetByProduct'
    ]);

    $r->addRoute('GET', '/ajax/ingredients', [
        'controller' => \App\Supplier\app\Http\Controllers\Ingredient\IngredientController::class,
        'method' => 'ajaxGetAll'
    ]);

    $r->addRoute('POST', '/ajax/catalog/products/{productId:\d+}/ingredients', [
        'controller' => \App\Supplier\app\Http\Controllers\Ingredient\IngredientController::class,
        'method' => 'ajaxAssignToProduct'
    ]);

    $r->addRoute('DELETE', '/ajax/catalog/products/{productId:\d+}/ingredients/{ingredientId:\d+}', [
        'controller' => \App\Supplier\app\Http\Controllers\Ingredient\IngredientController::class,
        'method' => 'ajaxUnassignFromProduct'
    ]);

};