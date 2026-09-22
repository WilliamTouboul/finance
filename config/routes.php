<?php

declare(strict_types=1);

use App\Controller\AuthController;
use App\Controller\DashboardController;
use App\Core\Router;

/**
 * Table de routage.
 *
 * Les chemins sont en francais : ils font partie de l'interface, au meme titre
 * que les libelles affiches.
 */
return static function (Router $router): void {
    // Acces libre
    $router->get('/connexion',  [AuthController::class, 'showLogin']);
    $router->post('/connexion', [AuthController::class, 'login']);
    $router->post('/deconnexion', [AuthController::class, 'logout']);

    // Acces protege : chaque action appelle requireUser() en premiere ligne.
    $router->get('/', [DashboardController::class, 'index']);
};
