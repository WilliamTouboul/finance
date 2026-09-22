<?php
/**
 * @var \App\Model\User $user
 */

use App\Core\Money;
use App\Core\View;
?>
<div class="page-head">
    <h1 class="page-head__title">Tableau de bord</h1>
    <p class="page-head__subtitle">Vue d'ensemble de vos comptes</p>
</div>

<section class="stat-row" aria-label="Chiffres clés">
    <article class="stat">
        <h2 class="stat__label">Solde</h2>
        <p class="stat__value"><?= View::e(Money::format(0)) ?></p>
    </article>

    <article class="stat">
        <h2 class="stat__label">Entrées du mois</h2>
        <p class="stat__value stat__value--positive"><?= View::e(Money::format(0)) ?></p>
    </article>

    <article class="stat">
        <h2 class="stat__label">Sorties du mois</h2>
        <p class="stat__value stat__value--negative"><?= View::e(Money::format(0)) ?></p>
    </article>
</section>

<section class="panel">
    <div class="panel__head">
        <h2 class="panel__title">Derniers flux</h2>
    </div>

    <div class="empty">
        <p class="empty__title">Aucune opération pour le moment</p>
        <p class="empty__text">Les opérations que vous saisirez apparaîtront ici, de la plus récente à la plus ancienne.</p>
        <a class="btn btn--primary" href="/operations/nouvelle">Ajouter une opération</a>
    </div>
</section>
