<?php
/**
 * Formulaire de saisie, partage entre creation et modification.
 *
 * @var \App\Model\Operation|null $operation null en creation
 * @var array<int, \App\Model\Tag> $allTags
 * @var array{label: string, amount: string, direction: string, date: string, note: string, tags: array<int, int>} $values
 * @var array<int, string> $errors
 */

use App\Core\Csrf;
use App\Core\View;

$isEdit = $operation !== null;
$action = $isEdit ? '/operations/' . $operation->id : '/operations';
?>
<div class="page-head">
    <h1 class="page-head__title"><?= $isEdit ? 'Modifier une opération' : 'Ajouter une opération' ?></h1>
    <p class="page-head__subtitle">
        Un montant négatif est une dépense, un montant positif une recette.
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

<form method="post" action="<?= View::e($action) ?>" class="panel panel--form">
    <?= Csrf::field() ?>

    <div class="field">
        <label class="field__label" for="label">Libellé</label>
        <input
            class="field__input"
            type="text"
            id="label"
            name="label"
            value="<?= View::e($values['label']) ?>"
            maxlength="150"
            placeholder="Achat raquette"
            required
            autofocus>
    </div>

    <div class="field-row">
        <div class="field">
            <span class="field__label">Sens</span>

            <div class="segmented">
                <label class="segmented__option">
                    <input type="radio" name="direction" value="expense"
                        <?= $values['direction'] === 'expense' ? 'checked' : '' ?>>
                    <span>Dépense</span>
                </label>

                <label class="segmented__option">
                    <input type="radio" name="direction" value="income"
                        <?= $values['direction'] === 'income' ? 'checked' : '' ?>>
                    <span>Recette</span>
                </label>
            </div>
        </div>

        <div class="field">
            <label class="field__label" for="amount">Montant</label>
            <div class="field__group">
                <input
                    class="field__input"
                    type="text"
                    inputmode="decimal"
                    id="amount"
                    name="amount"
                    value="<?= View::e($values['amount']) ?>"
                    placeholder="42,50"
                    required>
                <span class="field__suffix">€</span>
            </div>
        </div>

        <div class="field">
            <label class="field__label" for="date">Date</label>
            <input
                class="field__input"
                type="date"
                id="date"
                name="date"
                value="<?= View::e($values['date']) ?>"
                required>
        </div>
    </div>

    <div class="field">
        <span class="field__label">Tags</span>

        <?php if ($allTags === []): ?>
            <p class="field__hint">
                Aucun tag pour le moment. <a href="/tags">Créez-en un</a> pour classer vos opérations par pôle.
            </p>
        <?php else: ?>
            <div class="tag-picker">
                <?php foreach ($allTags as $tag): ?>
                    <label class="tag-pick">
                        <input
                            type="checkbox"
                            name="tags[]"
                            value="<?= (int) $tag->id ?>"
                            <?= in_array($tag->id, $values['tags'], true) ? 'checked' : '' ?>>
                        <span
                            class="tag-pick__badge"
                            style="--tag-color: <?= View::e($tag->color) ?>; --tag-text: <?= View::e($tag->readableTextColor()) ?>"
                        ><?= View::e($tag->name) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="field__hint">Plusieurs tags possibles sur une même opération.</p>
        <?php endif; ?>
    </div>

    <div class="field">
        <label class="field__label" for="note">Note <span class="field__optional">(facultatif)</span></label>
        <textarea
            class="field__input"
            id="note"
            name="note"
            rows="2"
            maxlength="1000"><?= View::e($values['note']) ?></textarea>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn--primary">
            <?= $isEdit ? 'Enregistrer les modifications' : 'Ajouter l\'opération' ?>
        </button>

        <a class="btn" href="/operations?p=<?= View::e(substr($values['date'], 0, 7)) ?>">Annuler</a>
    </div>
</form>
