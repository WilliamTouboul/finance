<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\UnauthorizedException;
use App\Core\View;
use App\Service\Auth;

// Autoload, journalisation et masquage des arguments dans les traces.
$root = require dirname(__DIR__) . '/config/bootstrap.php';

try {
    Config::load($root . '/config/config.php');
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $e->getMessage();
    exit;
}

// En developpement les erreurs s'affichent ; en production elles sont
// uniquement journalisees. Une trace d'erreur affichee en production revele
// l'arborescence du serveur et parfois des identifiants.
$isDev = Config::isDev();
ini_set('display_errors', $isDev ? '1' : '0');

View::setViewPath($root . '/views');

Session::start();

// Construit ici et injecte dans les controleurs par le routeur : une seule
// instance pour toute la requete, donc un seul chargement de l'utilisateur.
$auth = new Auth();

$router = new Router(static fn (string $class): object => new $class($auth));
(require $root . '/config/routes.php')($router);

$request = Request::fromGlobals();

try {
    $response = $router->dispatch($request);
} catch (UnauthorizedException) {
    // Page protegee demandee sans session valide.
    Session::flash('info', 'Merci de vous connecter pour accéder à cette page.');
    $response = Response::redirect('/connexion');
} catch (NotFoundException) {
    $response = Response::html(
        View::render('error/404', [
            'appName'     => (string) Config::get('app_name', 'Finance'),
            'pageTitle'   => 'Page introuvable',
            'currentUser' => $auth->user(),
            'flashes'     => [],
        ]),
        404
    );
} catch (Throwable $e) {
    error_log((string) $e);

    $response = Response::html(
        View::render('error/500', [
            'appName'     => (string) Config::get('app_name', 'Finance'),
            'pageTitle'   => 'Erreur',
            'currentUser' => null,
            'flashes'     => [],
            'detail'      => $isDev ? (string) $e : null,
        ]),
        500
    );
}

$response->send();
