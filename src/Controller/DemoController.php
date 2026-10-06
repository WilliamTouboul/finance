<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Service\Auth;
use App\Service\Demo;
use DateTimeImmutable;
use Throwable;

/**
 * Acces a la demonstration.
 *
 * L'ouverture passe par POST et non par GET, alors qu'un lien serait plus
 * commode a partager : creer un compte est une ecriture, et un GET se trouve
 * declenche par les prechargements de navigateur, les apercus de lien dans
 * les messageries et les robots d'indexation. Le lien partageable est donc une
 * page de presentation, d'ou part un vrai formulaire.
 */
final class DemoController extends BaseController
{
    private Demo $demo;

    public function __construct(Auth $auth)
    {
        parent::__construct($auth);

        $this->demo = new Demo();
    }

    /**
     * Page de presentation, c'est elle dont on partage l'adresse.
     */
    public function landing(Request $request): Response
    {
        if ($this->auth->check()) {
            return $this->redirect('/');
        }

        return $this->view('demo/landing', [
            'pageTitle' => 'Démonstration',
        ], 200, 'layout/auth');
    }

    /**
     * Fabrique un compte garni et y connecte le visiteur.
     */
    public function start(Request $request): Response
    {
        if (!Csrf::isValid($request->input(Csrf::FIELD_NAME))) {
            return $this->redirect('/demo');
        }

        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        try {
            $user = $this->demo->createAccount($ip);
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());

            return $this->redirect('/demo');
        }

        $this->demo->recordOpening($ip);
        $this->auth->login($user);

        Session::flash('success', 'Bienvenue dans la démonstration : tout est modifiable, rien n\'est conservé.');

        // On fait atterrir le visiteur sur le dernier mois complet plutot que
        // sur le mois en cours. Ouvrir la demonstration un 2 du mois donnerait
        // un camembert a deux parts, des budgets a zero et une courbe plate sur
        // trente jours -- une premiere impression qui ne refleterait rien.
        $lastFullMonth = (new DateTimeImmutable('first day of this month'))->modify('-1 month');

        return $this->redirect('/?p=' . $lastFullMonth->format('Y-m'));
    }
}
