<?php
/**
 * Liste d'operations, regroupees par jour.
 *
 * @var array<int, \App\Model\Operation> $operations
 * @var bool                             $showActions
 * @var string                           $returnTo  URL de retour apres suppression
 */

use App\Core\Csrf;
use App\Core\Money;
use App\Core\Period;
use App\Core\View;

$showActions = $showActions ?? false;

// Regroupement par date fait ici plutot qu'en SQL : la requete rend deja les
// lignes triees par date decroissante, il n'y a qu'a detecter les ruptures.
$groups = [];
foreach ($operations as $operation) {
    $groups[$operation->occurredOn->format('Y-m-d')][] = $operation;
}
?>
<ul class="op-list">
    <?php foreach ($groups as $day => $dayOperations): ?>
        <?php
        $dayTotal = array_sum(array_map(
            static fn (\App\Model\Operation $o): int => $o->amountCents,
            $dayOperations
        ));
        ?>
        <li class="op-day">
            <div class="op-day__head">
                <span class="op-day__date">
                    <?= View::e(Period::formatDayHeading($dayOperations[0]->occurredOn)) ?>
                </span>
                <span class="op-day__total amount <?= $dayTotal < 0 ? 'amount--negative' : 'amount--positive' ?>">
                    <?= View::e(Money::formatSigned($dayTotal)) ?>
                </span>
            </div>

            <ul class="op-day__items">
                <?php foreach ($dayOperations as $operation): ?>
                    <li class="op">
                        <div class="op__main">
                            <span class="op__label"><?= View::e($operation->label) ?></span>

                            <?php if ($operation->tags !== []): ?>
                                <span class="op__tags">
                                    <?php foreach ($operation->tags as $tag): ?>
                                        <span
                                            class="tag-badge"
                                            style="background: <?= View::e($tag->color) ?>; color: <?= View::e($tag->readableTextColor()) ?>"
                                        ><?= View::e($tag->name) ?></span>
                                    <?php endforeach; ?>
                                </span>
                            <?php endif; ?>

                            <?php if ($operation->note !== null): ?>
                                <span class="op__note" title="<?= View::e($operation->note) ?>">
                                    <?= View::e(mb_strimwidth($operation->note, 0, 70, '…')) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <span class="op__amount amount <?= $operation->isExpense() ? 'amount--negative' : 'amount--positive' ?>">
                            <?= View::e(Money::formatSigned($operation->amountCents)) ?>
                        </span>

                        <?php if ($showActions): ?>
                            <span class="op__actions">
                                <a class="btn btn--quiet btn--small" href="/operations/<?= (int) $operation->id ?>/modifier">Modifier</a>

                                <?php /* Message volontairement generique : injecter le libelle
                                         dans un attribut onsubmit demanderait un double
                                         echappement HTML puis JavaScript, construction fragile
                                         et classique porte d'entree XSS. */ ?>
                                <form
                                    method="post"
                                    action="/operations/<?= (int) $operation->id ?>/supprimer"
                                    class="op__delete"
                                    onsubmit="return confirm('Supprimer cette opération ?');">
                                    <?= Csrf::field() ?>
                                    <button type="submit" class="btn btn--quiet btn--small btn--danger">Supprimer</button>
                                </form>
                            </span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </li>
    <?php endforeach; ?>
</ul>
