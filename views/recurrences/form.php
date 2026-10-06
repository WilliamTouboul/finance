<?php
/**
 * @var \App\Model\Recurrence|null $recurrence
 * @var array<int, \App\Model\Tag> $allTags
 * @var array<string, mixed>       $values
 * @var array<int, string>         $errors
 */

use App\Core\Csrf;
use App\Core\Frequency;
use App\Core\View;

$isEdit = $recurrence !== null;
$action = $isEdit ? '/recurrences/' . $recurrence->id : '/recurrences';
?>
<div class="page-head">
    <h1 class="page-head__title"><?= $isEdit ? 'Modifier une récurrence' : 'Nouvelle récurrence' ?></h1>
    <p class="page-head__subtitle">
        <?php if ($isEdit): ?>
            Les opérations déjà enregistrées ne seront pas modifiées : seules les échéances à venir suivront ces valeurs.
        <?php else: ?>
            Décrivez ce qui revient régulièrement. Chaque échéance vous sera proposée à la date prévue.
        <?php endif; ?>
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
        <input class="field__input" type="text" id="label" name="label"
               value="<?= View::e($values['label']) ?>" maxlength="150"
               placeholder="Loyer" required autofocus>
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
            <label class="field__label" for="amount">Montant prévu</label>
            <div class="field__group">
                <input class="field__input" type="text" inputmode="decimal" id="amount" name="amount"
                       value="<?= View::e($values['amount']) ?>" placeholder="950,00" required>
                <span class="field__suffix">€</span>
            </div>
            <p class="field__hint">Ajustable au moment de valider chaque échéance.</p>
        </div>

        <div class="field">
            <label class="field__label" for="frequency">Rythme</label>
            <select class="field__input" id="frequency" name="frequency">
                <?php foreach (Frequency::cases() as $f): ?>
                    <option value="<?= View::e($f->value) ?>" <?= $values['frequency'] === $f->value ? 'selected' : '' ?>>
                        <?= View::e($f->label()) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="field-row field-row--two">
        <div class="field">
            <label class="field__label" for="starts_on">Première échéance</label>
            <input class="field__input" type="date" id="starts_on" name="starts_on"
                   value="<?= View::e($values['startsOn']) ?>" required>
            <p class="field__hint">
                C'est cette date qui fixe le jour : une première échéance le 5 revient le 5 de chaque mois.
            </p>
        </div>

        <div class="field">
            <label class="field__label" for="ends_on">
                Dernière échéance <span class="field__optional">(facultatif)</span>
            </label>
            <input class="field__input" type="date" id="ends_on" name="ends_on"
                   value="<?= View::e($values['endsOn']) ?>">
            <p class="field__hint">Pour un crédit ou un abonnement à durée limitée.</p>
        </div>
    </div>

    <div class="field">
        <span class="field__label">Tags</span>

        <?php if ($allTags === []): ?>
            <p class="field__hint">
                Aucun tag pour le moment. <a href="/tags">Créez-en un</a> pour classer ces opérations par pôle.
            </p>
        <?php else: ?>
            <div class="tag-picker">
                <?php foreach ($allTags as $tag): ?>
                    <div class="tag-choice">
                        <label class="tag-pick">
                            <input type="checkbox" name="tags[]" value="<?= (int) $tag->id ?>"
                                <?= in_array($tag->id, $values['tags'], true) ? 'checked' : '' ?>>
                            <span class="tag-pick__badge"
                                  style="--tag-color: <?= View::e($tag->color) ?>; --tag-text: <?= View::e($tag->readableTextColor()) ?>"
                            ><?= View::e($tag->name) ?></span>
                        </label>

                        <label class="tag-primary" title="Pôle principal : celui qui portera le montant">
                            <input type="radio" name="primary_tag" value="<?= (int) $tag->id ?>"
                                <?= $values['primaryTag'] === $tag->id ? 'checked' : '' ?>>
                            <span class="tag-primary__star" aria-hidden="true">★</span>
                            <span class="visually-hidden">Définir « <?= View::e($tag->name) ?> » comme pôle principal</span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="field__hint">Ces tags seront recopiés sur chaque opération générée.</p>
        <?php endif; ?>
    </div>

    <div class="field">
        <label class="field__label" for="note">Note <span class="field__optional">(facultatif)</span></label>
        <textarea class="field__input" id="note" name="note" rows="2" maxlength="1000"><?= View::e($values['note']) ?></textarea>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn--primary">
            <?= $isEdit ? 'Enregistrer les modifications' : 'Créer la récurrence' ?>
        </button>
        <a class="btn" href="/recurrences">Annuler</a>
    </div>
</form>
