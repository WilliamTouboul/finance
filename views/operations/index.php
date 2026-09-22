<?php
/**
 * @var \App\Core\Period                 $period
 * @var array<int, \App\Model\Operation> $operations
 * @var array{income: int, expense: int} $totals
 * @var array<int, \App\Model\Tag>       $allTags
 * @var int|null                         $tagFilter
 */

use App\Core\Money;
use App\Core\View;

$filterSuffix = $tagFilter !== null ? '&tag=' . $tagFilter : '';
?>
<div class="page-head page-head--with-action">
    <div>
        <h1 class="page-head__title">Opérations</h1>
        <p class="page-head__subtitle">
            <?= (int) count($operations) ?> opération<?= count($operations) > 1 ? 's' : '' ?>
            · <?= View::e(Money::formatSigned($totals['income'] + $totals['expense'])) ?> sur la période
        </p>
    </div>

    <a class="btn btn--primary" href="/operations/nouvelle?p=<?= View::e($period->toParam()) ?>">
        <span aria-hidden="true">+</span> Ajouter une opération
    </a>
</div>

<?= View::partial('partials/period-nav', [
    'period'  => $period,
    'baseUrl' => '/operations',
    'extra'   => $filterSuffix,
]) ?>

<?php if ($allTags !== []): ?>
    <div class="filter-bar">
        <span class="filter-bar__label">Filtrer :</span>

        <a
            class="tag-filter<?= $tagFilter === null ? ' tag-filter--active' : '' ?>"
            href="/operations?p=<?= View::e($period->toParam()) ?>">Tous</a>

        <?php foreach ($allTags as $tag): ?>
            <a
                class="tag-filter<?= $tagFilter === $tag->id ? ' tag-filter--active' : '' ?>"
                href="/operations?p=<?= View::e($period->toParam()) ?>&tag=<?= (int) $tag->id ?>">
                <span class="tag-filter__dot" style="background: <?= View::e($tag->color) ?>"></span>
                <?= View::e($tag->name) ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<section class="panel">
    <?php if ($operations === []): ?>
        <div class="empty">
            <p class="empty__title">Aucune opération sur cette période</p>
            <p class="empty__text">
                <?php if ($tagFilter !== null): ?>
                    Aucune opération ne porte ce tag sur la période consultée.
                <?php else: ?>
                    Changez de période avec les flèches ci-dessus, ou saisissez une opération.
                <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <?= View::partial('partials/operation-list', [
            'operations'  => $operations,
            'showActions' => true,
        ]) ?>
    <?php endif; ?>
</section>
