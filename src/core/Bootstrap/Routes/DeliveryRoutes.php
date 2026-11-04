<?php
use App\Kitchen\app\Http\Controllers\Material\DeliveryController;
use FastRoute\RouteCollector;
return function(RouteCollector $r): void {
    $r->addRoute('GET','/deliveries',['controller'=>DeliveryController::class,'method'=>'index']);
    $r->addRoute('GET','/deliveries/{id:\d+}',['controller'=>DeliveryController::class,'method'=>'detail']);
    $r->addRoute('POST','/ajax/deliveries/{id:\d+}/receive',['controller'=>DeliveryController::class,'method'=>'receive']);
};
