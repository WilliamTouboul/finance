<?php
/** @var string|null $detail */

use App\Core\View;
?>
<div class="page-head">
    <h1 class="page-head__title">Une erreur est survenue</h1>
    <p class="page-head__subtitle">L'opération n'a pas pu aboutir.</p>
</div>

<?php if ($detail !== null): ?>
    <pre class="trace"><?= View::e($detail) ?></pre>
<?php endif; ?>

<p><a class="btn" href="/">Retour au tableau de bord</a></p>
