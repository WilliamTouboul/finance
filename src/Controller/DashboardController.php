<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Period;
use App\Core\PieChart;
use App\Core\Request;
use App\Core\Response;
use App\Model\OperationFilter;
use App\Repository\OperationRepository;
use App\Service\Auth;

/**
 * Tableau de bord, reserve a l'utilisateur connecte.
 *
 * Trois indicateurs, la repartition des depenses par pole et les derniers
 * mouvements, le tout cadre sur la periode consultee.
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

        $operations = $this->operations->findBy($user->id, new OperationFilter($period));

        $pie    = new PieChart();
        $slices = $pie->computeSlices($this->expenseData($user->id, $period));

        return $this->view('dashboard/index', [
            'pageTitle'   => 'Tableau de bord',
            'period'      => $period,
            // Solde cumule a la fin de la periode, et non solde de la periode :
            // la question posee est "ou j'en etais a cette date".
            'balance'     => $this->operations->balanceAsOf($user->id, $period->endSql()),
            'totals'      => $this->operations->totalsFor($user->id, new OperationFilter($period)),
            'operations'  => array_slice($operations, 0, self::PREVIEW_SIZE),
            'totalCount'  => count($operations),
            'previewSize' => self::PREVIEW_SIZE,
            'pie'         => $pie,
            'slices'      => $slices,
        ]);
    }

    /**
     * Depenses de la periode mises en forme pour le camembert.
     *
     * Seul le tag principal porte le montant : une operation a plusieurs tags
     * ne compte que dans un pole, et la somme des parts egale exactement les
     * depenses de la periode.
     *
     * @return array<int, array{label: string, value: int, color: string, link: string|null}>
     */
    private function expenseData(int $userId, Period $period): array
    {
        return array_map(
            static function (array $row) use ($period): array {
                $tag = $row['tag'];

                return [
                    'label' => $tag?->name ?? 'Non classé',
                    'value' => $row['total'],
                    'color' => $tag?->color ?? '#9CA3AF',
                    // Sans tag principal, aucune liste a proposer : filtrer sur
                    // "rien" n'a pas de sens.
                    'link'  => $tag === null
                        ? null
                        : '/operations?p=' . $period->toParam()
                            . '&sens=' . OperationFilter::DIRECTION_EXPENSE
                            . '&tags%5B%5D=' . $tag->id,
                ];
            },
            $this->operations->expensesByPrimaryTag($userId, $period)
        );
    }
}
