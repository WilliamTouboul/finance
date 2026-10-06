<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\LineChart;
use App\Core\Money;
use App\Core\Period;
use App\Core\PieChart;
use App\Core\Request;
use App\Core\Response;
use App\Model\OperationFilter;
use App\Repository\BudgetRepository;
use App\Repository\OperationRepository;
use App\Repository\RecurrenceRepository;
use App\Service\Auth;

/**
 * Tableau de bord, reserve a l'utilisateur connecte.
 *
 * Indicateurs, courbe du solde, repartition par pole, budgets et derniers
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

    private RecurrenceRepository $recurrences;

    private BudgetRepository $budgets;

    public function __construct(Auth $auth)
    {
        parent::__construct($auth);

        $this->operations  = new OperationRepository();
        $this->recurrences = new RecurrenceRepository();
        $this->budgets     = new BudgetRepository();
    }

    public function index(Request $request): Response
    {
        $user   = $this->requireUser();
        $period = Period::fromParam($request->query('p'));

        $operations = $this->operations->findBy($user->id, new OperationFilter($period));

        $pie    = new PieChart();
        $slices = $pie->computeSlices($this->expenseData($user->id, $period));

        $series = $this->operations->dailyBalance($user->id, $period);

        return $this->view('dashboard/index', [
            'pageTitle'    => 'Tableau de bord',
            'period'       => $period,
            // Solde cumule a la fin de la periode, et non solde de la periode :
            // la question posee est "ou j'en etais a cette date".
            'balance'      => $this->operations->balanceAsOf($user->id, $period->endSql()),
            'totals'       => $this->operations->totalsFor($user->id, new OperationFilter($period)),
            'operations'   => array_slice($operations, 0, self::PREVIEW_SIZE),
            'totalCount'   => count($operations),
            'previewSize'  => self::PREVIEW_SIZE,
            'pie'          => $pie,
            'slices'       => $slices,
            'lineChart'    => new LineChart(),
            'balanceValues' => array_map(static fn (array $p): int => $p['balance'], $series),
            'balanceLabels' => array_map(
                static fn (array $p): string => Period::formatLongDate($p['date'])
                    . ' · ' . Money::format($p['balance']),
                $series
            ),
            // Les budgets se definissent au mois : les afficher sur une journee
            // donnerait un pourcentage denue de sens.
            'budgets'      => $period->monthsCovered() > 0
                ? $this->budgets->withSpending($user->id, $period)
                : [],
            'pendingCount' => $this->recurrences->countPending($user->id),
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
