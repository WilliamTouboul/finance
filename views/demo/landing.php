<?php
/**
 * Page de presentation de la demonstration. C'est son adresse que l'on partage.
 */

use App\Core\Csrf;
?>
<div class="card">
    <h2 class="card__title">Essayer l'application</h2>

    <p class="demo-intro">
        Un compte de démonstration va être créé, garni de trois mois d'opérations,
        de tags, de récurrences et de budgets.
    </p>

    <ul class="demo-points">
        <li>Tout est modifiable : saisissez, corrigez, supprimez librement.</li>
        <li>Les données sont les vôtres le temps de la visite, personne d'autre n'y accède.</li>
        <li>Le compte et son contenu sont effacés automatiquement au bout de 24 heures.</li>
    </ul>

    <form method="post" action="/demo" class="form">
        <?= Csrf::field() ?>
        <button type="submit" class="btn btn--primary btn--block">Démarrer la démonstration</button>
    </form>
</div>

<p class="auth__footnote">
    <a href="/connexion">Retour à la connexion</a>
</p>
