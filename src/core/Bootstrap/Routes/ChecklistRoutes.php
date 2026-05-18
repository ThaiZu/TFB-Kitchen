<?php

use FastRoute\RouteCollector;

return function (RouteCollector $r) {

    $r->addRoute('GET', '/checklists', [
        'controller' => \App\Kitchen\app\Http\Controllers\Checklist\ChecklistController::class,
        'method'     => 'index',
    ]);

    $r->addRoute('POST', '/checklists/tasks/{taskId}/complete', [
        'controller' => \App\Kitchen\app\Http\Controllers\Checklist\ChecklistController::class,
        'method'     => 'completeTask',
    ]);
};
