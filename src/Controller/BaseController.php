<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Model\User;
use App\Service\Auth;

/**
 * Base des controleurs de l'application.
 *
 * Separee de App\Core\Controller a dessein : le noyau ne doit rien savoir du
 * metier. Core\Controller sait rendre une vue, point. C'est ici, dans la
 * couche applicative, qu'on lui ajoute la notion d'utilisateur connecte.
 */
abstract class BaseController extends Controller
{
    public function __construct(
        protected readonly Auth $auth,
    ) {
    }

    /**
     * Ajoute a chaque vue l'utilisateur courant et les messages en attente,
     * pour que le gabarit n'ait pas a aller les chercher lui-meme.
     *
     * @param array<string, mixed> $data
     */
    protected function view(
        string $template,
        array $data = [],
        int $status = 200,
        string $layout = 'layout/base',
    ): Response {
        return parent::view(
            $template,
            $data + [
                'currentUser' => $this->auth->user(),
                // takeFlashes() consomme les messages : ils ne s'afficheront
                // qu'une seule fois, au premier rendu apres la redirection.
                'flashes'     => Session::takeFlashes(),
            ],
            $status,
            $layout,
        );
    }

    /**
     * A appeler en premiere ligne de toute action protegee.
     *
     * @throws \App\Core\UnauthorizedException interceptee par le front controller.
     */
    protected function requireUser(): User
    {
        return $this->auth->requireUser();
    }
}
