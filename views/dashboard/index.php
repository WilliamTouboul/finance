<?php
/**
 * @var \App\Core\Period                 $period
 * @var int                              $balance
 * @var array{income: int, expense: int} $totals
 * @var array<int, \App\Model\Operation> $operations
 * @var int                              $totalCount
 * @var int                              $previewSize
 * @var \App\Core\PieChart               $pie
 * @var array<int, array<string, mixed>> $slices
 * @var \App\Core\LineChart              $lineChart
 * @var array<int, int>                  $balanceValues
 * @var array<int, string>               $balanceLabels
 * @var array<int, \App\Model\Budget>    $budgets
 * @var int                              $pendingCount
 */

use App\Core\Money;
use App\Core\View;
?>
<div class="page-head">
    <h1 class="page-head__title">Tableau de bord</h1>
    <p class="page-head__subtitle">Vue d'ensemble de vos comptes</p>
</div>

<?php if ($pendingCount > 0): ?>
    <div class="notice notice--info" role="status">
        <strong><?= (int) $pendingCount ?> échéance<?= $pendingCount > 1 ? 's' : '' ?> récurrente<?= $pendingCount > 1 ? 's' : '' ?></strong>
        <span>arrivée<?= $pendingCount > 1 ? 's' : '' ?> à terme et en attente de votre validation.</span>
        <a href="/recurrences">Les examiner</a>
    </div>
<?php endif; ?>

<?= View::partial('partials/period-nav', ['period' => $period, 'baseUrl' => '/']) ?>

<section class="stat-row" aria-label="Chiffres clés">
    <article class="stat">
        <h2 class="stat__label">Solde au <?= View::e($period->end->format('d/m/Y')) ?></h2>
        <p class="stat__value <?= $balance < 0 ? 'stat__value--negative' : '' ?>">
            <?= View::e(Money::format($balance)) ?>
        </p>
        <p class="stat__hint">Cumul de tout l'historique</p>
    </article>

    <article class="stat">
        <h2 class="stat__label">Entrées</h2>
        <p class="stat__value stat__value--positive"><?= View::e(Money::format($totals['income'])) ?></p>
        <p class="stat__hint stat__hint--period"><?= View::e($period->label()) ?></p>
    </article>

    <article class="stat">
        <h2 class="stat__label">Sorties</h2>
        <p class="stat__value stat__value--negative"><?= View::e(Money::format($totals['expense'])) ?></p>
        <p class="stat__hint stat__hint--period"><?= View::e($period->label()) ?></p>
    </article>
</section>

<?php if ($balanceValues !== []): ?>
    <section class="panel">
        <div class="panel__head">
            <h2 class="panel__title">Évolution du solde</h2>
            <span class="panel__note">Jour par jour sur la période</span>
        </div>

        <div class="chart-frame">
            <?php /* SVG produit par LineChart, qui echappe deja ses propres valeurs. */ ?>
            <?= $lineChart->render($balanceValues, $balanceLabels) ?>

            <div class="chart-frame__bounds">
                <span><?= View::e(Money::format(min($balanceValues))) ?></span>
                <span><?= View::e(Money::format(max($balanceValues))) ?></span>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($slices !== []): ?>
    <section class="panel">
        <div class="panel__head">
            <h2 class="panel__title">Dépenses par pôle</h2>
            <span class="panel__note">Selon le tag principal de chaque opération</span>
        </div>

        <div class="pie-layout">
            <div class="pie-layout__chart">
                <?= $pie->render($slices) ?>
            </div>

            <ul class="legend">
                <?php foreach ($slices as $slice): ?>
                    <li class="legend__item">
                        <span class="legend__dot" style="background: <?= View::e($slice['color']) ?>"></span>

                        <span class="legend__label">
                            <?php if ($slice['link'] !== null): ?>
                                <a href="<?= View::e($slice['link']) ?>"><?= View::e($slice['label']) ?></a>
                            <?php else: ?>
                                <?= View::e($slice['label']) ?>
                            <?php endif; ?>
                        </span>

                        <span class="legend__share"><?= View::e($pie->formatShare($slice['share'])) ?></span>
                        <span class="legend__value amount"><?= View::e(Money::format($slice['value'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
<?php endif; ?>

<?php if ($budgets !== []): ?>
    <section class="panel">
        <div class="panel__head">
            <h2 class="panel__title">Budgets</h2>
            <a class="panel__link" href="/tags">Modifier</a>
        </div>

        <ul class="budget-list">
            <?php foreach ($budgets as $budget): ?>
                <li class="budget budget--<?= View::e($budget->level()) ?>">
                    <div class="budget__head">
                        <span class="tag-badge"
                              style="background: <?= View::e($budget->tag->color) ?>; color: <?= View::e($budget->tag->readableTextColor()) ?>"
                        ><?= View::e($budget->tag->name) ?></span>

                        <span class="budget__figures amount">
                            <?= View::e(Money::format($budget->spentCents)) ?>
                            <span class="budget__ceiling">/ <?= View::e(Money::format($budget->ceilingCents())) ?></span>
                        </span>
                    </div>

                    <div class="budget__bar" role="img"
                         aria-label="<?= View::e(number_format($budget->share() * 100, 0) . ' % du budget consommé') ?>">
                        <span class="budget__fill" style="width: <?= View::e(number_format($budget->barShare() * 100, 2, '.', '')) ?>%"></span>
                    </div>

                    <p class="budget__note">
                        <?php if ($budget->isExceeded()): ?>
                            Dépassé de <?= View::e(Money::format(abs($budget->remainingCents()))) ?>
                        <?php else: ?>
                            Reste <?= View::e(Money::format($budget->remainingCents())) ?>
                        <?php endif; ?>
                    </p>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<section class="panel">
    <div class="panel__head">
        <h2 class="panel__title">Derniers flux</h2>

        <?php if ($totalCount > $previewSize): ?>
            <a class="panel__link" href="/operations?p=<?= View::e($period->toParam()) ?>">
                Voir les <?= (int) $totalCount ?> opérations
            </a>
        <?php endif; ?>
    </div>

    <?php if ($operations === []): ?>
        <div class="empty">
            <p class="empty__title">Aucune opération sur cette période</p>
            <p class="empty__text">
                Changez de période avec les flèches ci-dessus, ou saisissez votre première opération.
            </p>
            <a class="btn btn--primary" href="/operations/nouvelle?p=<?= View::e($period->toParam()) ?>">
                Ajouter une opération
            </a>
        </div>
    <?php else: ?>
        <?= View::partial('partials/operation-list', ['operations' => $operations]) ?>
    <?php endif; ?>
</section>
