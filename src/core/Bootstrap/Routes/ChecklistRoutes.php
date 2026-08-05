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

    // La prise de poste : le seul endroit où le PIN est saisi.
    $r->addRoute('POST', '/ajax/checklists/shift', [
        'controller' => \App\Kitchen\app\Http\Controllers\Checklist\ChecklistController::class,
        'method'     => 'openShift',
    ]);
    $r->addRoute('POST', '/ajax/checklists/shift/close', [
        'controller' => \App\Kitchen\app\Http\Controllers\Checklist\ChecklistController::class,
        'method'     => 'closeShift',
    ]);
};
