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
 */

use App\Core\Money;
use App\Core\View;
?>
<div class="page-head">
    <h1 class="page-head__title">Tableau de bord</h1>
    <p class="page-head__subtitle">Vue d'ensemble de vos comptes</p>
</div>

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

<?php if ($slices !== []): ?>
    <section class="panel">
        <div class="panel__head">
            <h2 class="panel__title">Dépenses par pôle</h2>
            <span class="panel__note">Selon le tag principal de chaque opération</span>
        </div>

        <div class="pie-layout">
            <div class="pie-layout__chart">
                <?php /* SVG produit par PieChart, qui echappe deja ses propres valeurs. */ ?>
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
