<?php
/**
 * @var \App\Model\OperationFilter       $filter
 * @var \App\Core\Period                 $period
 * @var array<int, \App\Model\Operation> $operations
 * @var array{income: int, expense: int} $totals
 * @var array<int, \App\Model\Tag>       $allTags
 */

use App\Core\Money;
use App\Core\View;
use App\Model\OperationFilter;

$count = count($operations);
?>
<div class="page-head page-head--with-action">
    <div>
        <h1 class="page-head__title">Opérations</h1>
        <p class="page-head__subtitle">
            <?= (int) $count ?> opération<?= $count > 1 ? 's' : '' ?>
            · <?= View::e(Money::formatSigned($totals['income'] + $totals['expense'])) ?> sur la période
        </p>
    </div>

    <div class="page-head__actions">
        <?php if ($count > 0): ?>
            <a class="btn" href="/operations/export?<?= View::e($filter->toQueryString()) ?>">
                Exporter en CSV
            </a>
        <?php endif; ?>

        <a class="btn btn--primary" href="/operations/nouvelle?p=<?= View::e($period->toParam()) ?>">
            <span aria-hidden="true">+</span> Ajouter une opération
        </a>
    </div>
</div>

<?php
// La navigation temporelle conserve les criteres en cours : changer de mois
// ne doit pas effacer une recherche.
$extra = preg_replace('/^p=[^&]*&?/', '', $filter->toQueryString());
?>
<?= View::partial('partials/period-nav', [
    'period'  => $period,
    'baseUrl' => '/operations',
    'extra'   => $extra !== '' ? '&' . $extra : '',
]) ?>

<form method="get" action="/operations" class="filters">
    <input type="hidden" name="p" value="<?= View::e($period->toParam()) ?>">

    <div class="filters__row">
        <div class="field field--grow">
            <label class="field__label" for="q">Rechercher</label>
            <input
                class="field__input"
                type="search"
                id="q"
                name="q"
                value="<?= View::e($filter->search) ?>"
                placeholder="Libellé ou note"
                maxlength="100">
        </div>

        <div class="field">
            <label class="field__label" for="sens">Sens</label>
            <select class="field__input" id="sens" name="sens">
                <option value="">Tout</option>
                <option value="<?= OperationFilter::DIRECTION_EXPENSE ?>"
                    <?= $filter->direction === OperationFilter::DIRECTION_EXPENSE ? 'selected' : '' ?>>Dépenses</option>
                <option value="<?= OperationFilter::DIRECTION_INCOME ?>"
                    <?= $filter->direction === OperationFilter::DIRECTION_INCOME ? 'selected' : '' ?>>Recettes</option>
            </select>
        </div>

        <div class="field field--narrow">
            <label class="field__label" for="min">Montant min</label>
            <input class="field__input" type="text" inputmode="decimal" id="min" name="min"
                   value="<?= $filter->minCents !== null ? View::e(Money::toInput($filter->minCents)) : '' ?>"
                   placeholder="0">
        </div>

        <div class="field field--narrow">
            <label class="field__label" for="max">Montant max</label>
            <input class="field__input" type="text" inputmode="decimal" id="max" name="max"
                   value="<?= $filter->maxCents !== null ? View::e(Money::toInput($filter->maxCents)) : '' ?>"
                   placeholder="∞">
        </div>
    </div>

    <?php if ($allTags !== []): ?>
        <div class="filters__tags">
            <span class="field__label">Tags</span>

            <div class="tag-picker">
                <?php foreach ($allTags as $tag): ?>
                    <label class="tag-pick">
                        <input type="checkbox" name="tags[]" value="<?= (int) $tag->id ?>"
                            <?= in_array($tag->id, $filter->tagIds, true) ? 'checked' : '' ?>>
                        <span class="tag-pick__badge"
                              style="--tag-color: <?= View::e($tag->color) ?>; --tag-text: <?= View::e($tag->readableTextColor()) ?>"
                        ><?= View::e($tag->name) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="filters__actions">
        <button type="submit" class="btn btn--primary">Filtrer</button>

        <?php if ($filter->hasCriteria()): ?>
            <a class="btn btn--quiet" href="/operations?p=<?= View::e($period->toParam()) ?>">
                Effacer les filtres
            </a>
        <?php endif; ?>
    </div>
</form>

<section class="panel">
    <?php if ($operations === []): ?>
        <div class="empty">
            <p class="empty__title">Aucune opération</p>
            <p class="empty__text">
                <?php if ($filter->hasCriteria()): ?>
                    Aucune opération ne correspond à ces critères sur la période consultée.
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
