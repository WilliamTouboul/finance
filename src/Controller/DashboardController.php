<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Period;
use App\Core\Request;
use App\Core\Response;
use App\Repository\OperationRepository;
use App\Service\Auth;

/**
 * Tableau de bord, reserve a l'utilisateur connecte.
 *
 * Trois indicateurs et les derniers mouvements, le tout cadre sur la periode
 * consultee : un jour, un mois ou une annee.
 */
final class DashboardController extends BaseController
{
    /**
     * Nombre de mouvements montres sur l'accueil. Au-dela, un lien renvoie
     * vers la liste complete : le tableau de bord doit tenir dans un ecran.
     */
    private const PREVIEW_SIZE = 8;

    private OperationRepository $operations;

    public function __construct(Auth $auth)
    {
        parent::__construct($auth);

        $this->operations = new OperationRepository();
    }

    public function index(Request $request): Response
    {
        $user   = $this->requireUser();
        $period = Period::fromParam($request->query('p'));

        $operations = $this->operations->listForPeriod($user->id, $period);

        return $this->view('dashboard/index', [
            'pageTitle'  => 'Tableau de bord',
            'period'     => $period,
            // Solde cumule a la fin de la periode, et non solde de la periode :
            // la question posee est "ou j'en etais a cette date".
            'balance'    => $this->operations->balanceAsOf($user->id, $period->endSql()),
            'totals'     => $this->operations->totalsForPeriod($user->id, $period),
            'operations' => array_slice($operations, 0, self::PREVIEW_SIZE),
            'totalCount' => count($operations),
            'previewSize' => self::PREVIEW_SIZE,
        ]);
    }
}
