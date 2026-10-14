<?php

use FastRoute\RouteCollector;

return function (RouteCollector $r): void {
    $r->addRoute('GET','/production/receive',[
        'controller'=>\App\Kitchen\app\Http\Controllers\ProductionList\ProductionListController::class,'method'=>'receipt',
    ]);
    $r->addRoute('GET','/production/pdf',[
        'controller'=>\App\Kitchen\app\Http\Controllers\ProductionList\ProductionListController::class,'method'=>'pdf',
    ]);
    $r->addRoute('GET', '/production-list', [
        'controller' => \App\Kitchen\app\Http\Controllers\ProductionList\ProductionListController::class,
        'method' => 'index',
    ]);
    foreach (['save', 'receive'] as $action) {
        $r->addRoute('POST', '/production-list/' . $action, [
            'controller' => \App\Kitchen\app\Http\Controllers\ProductionList\ProductionListController::class,
            'method' => $action,
        ]);
    }
};
