<?php
/**
 * @var array<int, \App\Model\Tag> $tags
 * @var array<int, int>            $budgets montants par identifiant de tag
 * @var string                     $formName
 * @var string                     $formColor
 * @var array<int, string>         $errors
 */

use App\Core\Csrf;
use App\Core\Money;
use App\Core\View;

$budgets = $budgets ?? [];
?>
<div class="page-head">
    <h1 class="page-head__title">Tags</h1>
    <p class="page-head__subtitle">
        Les pôles qui servent à classer vos opérations. Une opération peut en porter plusieurs.
    </p>
</div>

<?php if ($errors !== []): ?>
    <div class="form-error" role="alert">
        <ul class="form-error__list">
            <?php foreach ($errors as $error): ?>
                <li><?= View::e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="/tags" class="panel panel--form panel--inline">
    <?= Csrf::field() ?>

    <div class="field field--grow">
        <label class="field__label" for="name">Nouveau tag</label>
        <input
            class="field__input"
            type="text"
            id="name"
            name="name"
            value="<?= View::e($formName) ?>"
            maxlength="50"
            placeholder="loisirs"
            required>
    </div>

    <div class="field">
        <label class="field__label" for="color">Couleur</label>
        <input class="field__color" type="color" id="color" name="color" value="<?= View::e($formColor) ?>">
    </div>

    <button type="submit" class="btn btn--primary">Créer</button>
</form>

<section class="panel">
    <div class="panel__head">
        <h2 class="panel__title">Vos tags</h2>
        <span class="panel__note">Le budget est mensuel et se compare aux dépenses du pôle principal</span>
    </div>

    <?php if ($tags === []): ?>
        <div class="empty">
            <p class="empty__title">Aucun tag pour le moment</p>
            <p class="empty__text">
                Créez vos pôles de dépense ci-dessus : loisirs, sport, alimentation, transport…
                Vous pourrez ensuite les associer à chaque opération.
            </p>
        </div>
    <?php else: ?>
        <ul class="tag-list">
            <?php foreach ($tags as $tag): ?>
                <li class="tag-row">
                    <span
                        class="tag-badge"
                        style="background: <?= View::e($tag->color) ?>; color: <?= View::e($tag->readableTextColor()) ?>"
                    ><?= View::e($tag->name) ?></span>

                    <span class="tag-row__usage">
                        <?php if ($tag->usageCount === 0): ?>
                            jamais utilisé
                        <?php else: ?>
                            <a href="/operations?tags%5B%5D=<?= (int) $tag->id ?>">
                                <?= (int) $tag->usageCount ?> opération<?= $tag->usageCount > 1 ? 's' : '' ?>
                            </a>
                        <?php endif; ?>
                    </span>

                    <?php /* Champ vide = pas de budget. Effacer la valeur le retire. */ ?>
                    <form method="post" action="/tags/<?= (int) $tag->id ?>/budget" class="tag-row__budget">
                        <?= Csrf::field() ?>
                        <label class="visually-hidden" for="budget-<?= (int) $tag->id ?>">
                            Budget mensuel de <?= View::e($tag->name) ?>
                        </label>
                        <input
                            class="field__input field__input--compact"
                            type="text"
                            inputmode="decimal"
                            id="budget-<?= (int) $tag->id ?>"
                            name="budget"
                            value="<?= isset($budgets[$tag->id]) ? View::e(Money::toInput($budgets[$tag->id])) : '' ?>"
                            placeholder="budget">
                        <span class="field__suffix">€/mois</span>
                        <button type="submit" class="btn btn--quiet btn--small">Enregistrer</button>
                    </form>

                    <span class="tag-row__actions">
                        <a class="btn btn--quiet btn--small" href="/tags/<?= (int) $tag->id ?>/modifier">Modifier</a>

                        <form
                            method="post"
                            action="/tags/<?= (int) $tag->id ?>/supprimer"
                            class="inline-form"
                            onsubmit="return confirm('Supprimer ce tag ? Les opérations concernées seront conservées.');">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn--quiet btn--small btn--danger">Supprimer</button>
                        </form>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
