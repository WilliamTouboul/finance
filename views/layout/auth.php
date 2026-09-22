<?php
/**
 * Layout de l'ecran de connexion : volontairement nu.
 *
 * Pas de navigation, pas de bouton d'action : tant que l'utilisateur n'est pas
 * authentifie, il n'y a rien d'autre a faire sur cette page.
 *
 * @var string                                        $appName
 * @var string|null                                   $pageTitle
 * @var string                                        $content
 * @var array<int, array{type: string, message: string}> $flashes
 */

use App\Core\View;

$title = $pageTitle !== null ? $pageTitle . ' · ' . $appName : $appName;
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
<body class="body--centered">
    <main class="auth">
        <h1 class="auth__brand"><?= View::e($appName) ?></h1>

        <?php foreach ($flashes as $flash): ?>
            <div class="notice notice--<?= View::e($flash['type']) ?>" role="status">
                <?= View::e($flash['message']) ?>
            </div>
        <?php endforeach; ?>

        <?= $content ?>
    </main>
</body>
</html>
