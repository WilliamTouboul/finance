<?php

/**
 * @var string|null $error
 * @var string|null $email
 */

use App\Core\Csrf;
use App\Core\View;
?>
<div class="card">
    <h2 class="card__title">Connexion</h2>

    <?php if ($error !== null): ?>
        <p class="form-error" role="alert"><?= View::e($error) ?></p>
    <?php endif; ?>

    <form method="post" action="/connexion" class="form" novalidate>
        <?= Csrf::field() ?>

        <div class="field">
            <label class="field__label" for="email">Adresse e-mail</label>
            <input
                class="field__input"
                type="email"
                id="email"
                name="email"
                value="<?= View::e($email) ?>"
                autocomplete="username"
                required
                autofocus>
        </div>

        <div class="field">
            <label class="field__label" for="password">Mot de passe</label>
            <input
                class="field__input"
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                required>
        </div>

        <button type="submit" class="btn btn--primary btn--block">Se connecter</button>
    </form>

    <?php /* Un formulaire distinct, et non un lien : ouvrir une demonstration
             cree un compte, donc c'est une ecriture. Un lien serait declenche
             par les prechargements de navigateur et les apercus de messagerie. */ ?>
    <div class="demo-access">
        <span class="demo-access__sep">ou</span>

        <form method="post" action="/demo" class="form">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn--block">Accéder à la démonstration</button>
        </form>

        <p class="demo-access__note">
            Compte d'essai garni de données fictives, sans inscription.
        </p>
    </div>
</div>

<p class="auth__footnote">
    Application privée.
</p>