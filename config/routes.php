<?php

declare(strict_types=1);

use App\Controller\AuthController;
use App\Controller\DashboardController;
use App\Controller\OperationController;
use App\Controller\RecurrenceController;
use App\Controller\TagController;
use App\Core\Router;

/**
 * Table de routage.
 *
 * Les chemins sont en francais : ils font partie de l'interface, au meme titre
 * que les libelles affiches.
 *
 * Les suppressions et modifications passent par POST, jamais par GET : une
 * action destructive accessible en GET peut etre declenchee par un simple lien
 * ou une balise image sur une page tierce, et se trouve prechargee par
 * certains navigateurs.
 */
return static function (Router $router): void {
    // Acces libre
    $router->get('/connexion',    [AuthController::class, 'showLogin']);
    $router->post('/connexion',   [AuthController::class, 'login']);
    $router->post('/deconnexion', [AuthController::class, 'logout']);

    // Acces protege : chaque action appelle requireUser() en premiere ligne.
    $router->get('/', [DashboardController::class, 'index']);

    $router->get('/operations',                   [OperationController::class, 'index']);
    $router->get('/operations/export',            [OperationController::class, 'export']);
    $router->get('/operations/nouvelle',          [OperationController::class, 'create']);
    $router->post('/operations',                  [OperationController::class, 'store']);
    $router->get('/operations/{id}/modifier',     [OperationController::class, 'edit']);
    $router->post('/operations/{id}',             [OperationController::class, 'update']);
    $router->post('/operations/{id}/supprimer',   [OperationController::class, 'delete']);

    $router->get('/recurrences',                  [RecurrenceController::class, 'index']);
    $router->get('/recurrences/nouvelle',         [RecurrenceController::class, 'create']);
    $router->post('/recurrences',                 [RecurrenceController::class, 'store']);
    $router->get('/recurrences/{id}/modifier',    [RecurrenceController::class, 'edit']);
    $router->post('/recurrences/{id}',            [RecurrenceController::class, 'update']);
    $router->post('/recurrences/{id}/basculer',   [RecurrenceController::class, 'toggle']);
    $router->post('/recurrences/{id}/supprimer',  [RecurrenceController::class, 'delete']);

    // Validation des echeances arrivees a terme.
    $router->post('/echeances/valider',           [RecurrenceController::class, 'confirm']);
    $router->post('/echeances/tout-valider',      [RecurrenceController::class, 'confirmAll']);

    $router->get('/tags',                   [TagController::class, 'index']);
    $router->post('/tags',                  [TagController::class, 'store']);
    $router->get('/tags/{id}/modifier',     [TagController::class, 'edit']);
    $router->post('/tags/{id}',             [TagController::class, 'update']);
    $router->post('/tags/{id}/budget',      [TagController::class, 'saveBudget']);
    $router->post('/tags/{id}/supprimer',   [TagController::class, 'delete']);
};
