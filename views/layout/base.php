<?php
/**
 * @var string                                        $appName
 * @var string|null                                   $pageTitle
 * @var string                                        $content
 * @var \App\Model\User|null                          $currentUser
 * @var array<int, array{type: string, message: string}> $flashes
 */

use App\Core\Csrf;
use App\Core\View;

$title = $pageTitle !== null ? $pageTitle . ' · ' . $appName : $appName;
$currentUser = $currentUser ?? null;
$flashes = $flashes ?? [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= View::e($title) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <header class="app-header">
        <div class="container app-header__inner">
            <a class="brand" href="/"><?= View::e($appName) ?></a>

            <?php if ($currentUser !== null): ?>
                <nav class="nav" aria-label="Navigation principale">
                    <a class="nav__link" href="/">Tableau de bord</a>
                    <a class="nav__link" href="/operations">Opérations</a>
                    <a class="nav__link" href="/tags">Tags</a>
                </nav>

                <a class="btn btn--primary" href="/operations/nouvelle">
                    <span aria-hidden="true">+</span> Ajouter une opération
                </a>

                <div class="user-menu">
                    <span class="avatar" title="<?= View::e($currentUser->displayName) ?>">
                        <?= View::e($currentUser->initials()) ?>
                    </span>

                    <form method="post" action="/deconnexion" class="user-menu__form">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn btn--quiet">Déconnexion</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <main class="container main">
        <?php foreach ($flashes as $flash): ?>
            <div class="notice notice--<?= View::e($flash['type']) ?>" role="status">
                <?= View::e($flash['message']) ?>
            </div>
        <?php endforeach; ?>

        <?= $content ?>
    </main>

    <footer class="app-footer">
        <div class="container">
            <span>Données privées — usage personnel</span>
        </div>
    </footer>
</body>
</html>
