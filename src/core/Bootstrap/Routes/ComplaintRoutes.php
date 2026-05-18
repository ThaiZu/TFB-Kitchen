<?php
use FastRoute\RouteCollector;
return function (RouteCollector $r) {
    $r->addRoute('GET', '/complaints', [
        'controller' => \App\Kitchen\app\Http\Controllers\Complaint\ComplaintController::class,
        'method'     => 'overview',
    ]);
    $r->addRoute('GET', '/complaints/new', [
        'controller' => \App\Kitchen\app\Http\Controllers\Complaint\ComplaintController::class,
        'method'     => 'create',
    ]);
    $r->addRoute('POST', '/complaints/new', [
        'controller' => \App\Kitchen\app\Http\Controllers\Complaint\ComplaintController::class,
        'method'     => 'create',
    ]);
    $r->addRoute('GET', '/ajax/complaints/attachment/{id:[^/]+}/presigned-url', [
        'controller' => \App\Kitchen\app\Http\Controllers\Complaint\ComplaintController::class,
        'method'     => 'ajaxPresignedUrl',
    ]);
};
