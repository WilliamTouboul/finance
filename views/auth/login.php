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
</div>

<p class="auth__footnote">
    Application privée. Les comptes sont créés depuis le serveur.
</p>
