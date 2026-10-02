<?php
use FastRoute\RouteCollector;
use App\Kitchen\app\Http\Controllers\Inventory\InventoryController;
return function(RouteCollector $r): void {
    $r->addRoute('GET','/inventory',['controller'=>InventoryController::class,'method'=>'index']);
    $r->addRoute('POST','/inventory/count',['controller'=>InventoryController::class,'method'=>'count']);
};
