<?php
/**
 * @var \App\Model\Tag     $tag
 * @var string             $formName
 * @var string             $formColor
 * @var array<int, string> $errors
 */

use App\Core\Csrf;
use App\Core\View;
?>
<div class="page-head">
    <h1 class="page-head__title">Modifier un tag</h1>
    <p class="page-head__subtitle">
        Le renommer met à jour toutes les opérations qui le portent.
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

<form method="post" action="/tags/<?= (int) $tag->id ?>" class="panel panel--form">
    <?= Csrf::field() ?>

    <div class="field-row">
        <div class="field field--grow">
            <label class="field__label" for="name">Nom</label>
            <input
                class="field__input"
                type="text"
                id="name"
                name="name"
                value="<?= View::e($formName) ?>"
                maxlength="50"
                required
                autofocus>
        </div>

        <div class="field">
            <label class="field__label" for="color">Couleur</label>
            <input class="field__color" type="color" id="color" name="color" value="<?= View::e($formColor) ?>">
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn--primary">Enregistrer</button>
        <a class="btn" href="/tags">Annuler</a>
    </div>
</form>
