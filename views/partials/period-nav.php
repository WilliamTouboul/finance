<?php
/**
 * Navigation temporelle : recul / avance, changement d'echelle, retour a aujourd'hui.
 *
 * @var \App\Core\Period $period
 * @var string           $baseUrl page sur laquelle la navigation agit
 * @var string           $extra   parametres a conserver dans les liens (filtre de tag)
 */

use App\Core\Granularity;
use App\Core\Period;
use App\Core\View;

$extra = $extra ?? '';
$link = static fn (Period $p): string => View::e($baseUrl . '?p=' . $p->toParam() . $extra);

// Sans parametre de periode, le controleur retombe sur le mois courant.
$todayUrl = View::e($baseUrl . ($extra !== '' ? '?' . ltrim($extra, '&') : ''));

// On ne propose pas d'avancer au-dela de la periode en cours : il n'y a rien
// a y voir, et cela donnerait l'impression que des donnees manquent.
$nextIsFuture = $period->next()->start > new DateTimeImmutable();
?>
<div class="period">
    <div class="period__browse">
        <a class="period__arrow" href="<?= $link($period->previous()) ?>" rel="prev" aria-label="Période précédente">‹</a>

        <span class="period__label"><?= View::e($period->label()) ?></span>

        <?php if ($nextIsFuture): ?>
            <span class="period__arrow period__arrow--disabled" aria-hidden="true">›</span>
        <?php else: ?>
            <a class="period__arrow" href="<?= $link($period->next()) ?>" rel="next" aria-label="Période suivante">›</a>
        <?php endif; ?>

        <?php if (!$period->isCurrent()): ?>
            <a class="period__today" href="<?= $todayUrl ?>">Aujourd'hui</a>
        <?php endif; ?>
    </div>

    <div class="period__scales" role="group" aria-label="Échelle de temps">
        <?php foreach (Granularity::cases() as $scale): ?>
            <?php $isCurrent = $scale === $period->granularity; ?>
            <a
                class="period__scale<?= $isCurrent ? ' period__scale--active' : '' ?>"
                href="<?= $link($period->withGranularity($scale)) ?>"
                <?= $isCurrent ? 'aria-current="true"' : '' ?>><?= View::e($scale->label()) ?></a>
        <?php endforeach; ?>
    </div>
</div>
