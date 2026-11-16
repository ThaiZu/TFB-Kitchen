<?php
use FastRoute\RouteCollector;
use App\Kitchen\app\Http\Controllers\ProductLabels\ProductLabelController;
return function(RouteCollector $r):void {
 $r->addRoute('GET','/product-labels',['controller'=>ProductLabelController::class,'method'=>'index']);
 foreach(['catalog','jobs'] as $action)$r->addRoute('GET','/ajax/product-labels/'.$action,['controller'=>ProductLabelController::class,'method'=>$action]);
 foreach(['create','reprint'] as $action)$r->addRoute('POST','/ajax/product-labels/'.$action,['controller'=>ProductLabelController::class,'method'=>$action]);
};
