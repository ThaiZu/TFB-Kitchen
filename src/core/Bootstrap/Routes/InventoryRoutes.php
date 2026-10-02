<?php
use FastRoute\RouteCollector;
use App\Kitchen\app\Http\Controllers\Inventory\InventoryController;
return function(RouteCollector $r): void {
    $r->addRoute('GET','/inventory',['controller'=>InventoryController::class,'method'=>'index']);
    $r->addRoute('POST','/inventory/count',['controller'=>InventoryController::class,'method'=>'count']);
    $r->addRoute('GET','/inventory/waste-data',['controller'=>InventoryController::class,'method'=>'wasteData']);
    $r->addRoute('POST','/inventory/waste',['controller'=>InventoryController::class,'method'=>'waste']);
    $r->addRoute('GET','/inventory/carryover-data',['controller'=>InventoryController::class,'method'=>'carryoverData']);
    $r->addRoute('POST','/inventory/carryover',['controller'=>InventoryController::class,'method'=>'carryover']);
};
