<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;

/**
 * Page d'accueil, reservee a l'utilisateur connecte.
 *
 * Les montants sont encore a zero : ils seront alimentes au bloc 3,
 * quand les operations existeront.
 */
final class DashboardController extends BaseController
{
    public function index(Request $request): Response
    {
        $user = $this->requireUser();

        return $this->view('dashboard/index', [
            'pageTitle' => 'Tableau de bord',
            'user'      => $user,
        ]);
    }
}
