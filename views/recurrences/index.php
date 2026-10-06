<?php
/**
 * @var array<int, \App\Model\Recurrence> $recurrences
 * @var array<int, array{recurrence: \App\Model\Recurrence, date: \DateTimeImmutable}> $pending
 */

use App\Core\Csrf;
use App\Core\Money;
use App\Core\Period;
use App\Core\View;
?>
<div class="page-head page-head--with-action">
    <div>
        <h1 class="page-head__title">Opérations récurrentes</h1>
        <p class="page-head__subtitle">
            Ce qui revient chaque mois. Rien n'est enregistré sans votre validation.
        </p>
    </div>

    <a class="btn btn--primary" href="/recurrences/nouvelle">
        <span aria-hidden="true">+</span> Nouvelle récurrence
    </a>
</div>

<?php if ($pending !== []): ?>
    <section class="panel panel--pending">
        <div class="panel__head">
            <h2 class="panel__title">
                <?= count($pending) ?> échéance<?= count($pending) > 1 ? 's' : '' ?> à valider
            </h2>

            <form method="post" action="/echeances/tout-valider" class="inline-form">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn--small">Tout valider</button>
            </form>
        </div>

        <ul class="due-list">
            <?php foreach ($pending as $item): ?>
                <?php $r = $item['recurrence']; ?>
                <li class="due">
                    <form method="post" action="/echeances/valider" class="due__form">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="recurrence_id" value="<?= (int) $r->id ?>">
                        <input type="hidden" name="date" value="<?= View::e($item['date']->format('Y-m-d')) ?>">

                        <span class="due__date"><?= View::e($item['date']->format('d/m/Y')) ?></span>

                        <span class="due__label">
                            <?= View::e($r->label) ?>
                            <?php foreach ($r->tagsPrimaryFirst() as $tag): ?>
                                <span class="tag-badge"
                                      style="background: <?= View::e($tag->color) ?>; color: <?= View::e($tag->readableTextColor()) ?>"
                                ><?= View::e($tag->name) ?></span>
                            <?php endforeach; ?>
                        </span>

                        <?php /* Le montant est modifiable : une facture varie d'un mois a l'autre. */ ?>
                        <span class="due__amount">
                            <input
                                class="field__input field__input--compact"
                                type="text"
                                inputmode="decimal"
                                name="amount"
                                value="<?= View::e(Money::toInput(abs($r->amountCents))) ?>"
                                aria-label="Montant de l'échéance">
                            <span class="field__suffix"><?= $r->isExpense() ? '€ dépense' : '€ recette' ?></span>
                        </span>

                        <button type="submit" class="btn btn--primary btn--small">Valider</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<section class="panel">
    <div class="panel__head">
        <h2 class="panel__title">Vos récurrences</h2>
    </div>

    <?php if ($recurrences === []): ?>
        <div class="empty">
            <p class="empty__title">Aucune récurrence</p>
            <p class="empty__text">
                Loyer, salaire, abonnements : décrivez-les une fois, et l'application
                vous proposera chaque échéance à valider le moment venu.
            </p>
            <a class="btn btn--primary" href="/recurrences/nouvelle">Créer une récurrence</a>
        </div>
    <?php else: ?>
        <ul class="rec-list">
            <?php foreach ($recurrences as $r): ?>
                <?php $next = $r->nextOccurrence(); ?>
                <li class="rec <?= $r->isActive ? '' : 'rec--paused' ?>">
                    <div class="rec__main">
                        <span class="rec__label"><?= View::e($r->label) ?></span>

                        <?php foreach ($r->tagsPrimaryFirst() as $tag): ?>
                            <span class="tag-badge"
                                  style="background: <?= View::e($tag->color) ?>; color: <?= View::e($tag->readableTextColor()) ?>"
                            ><?= View::e($tag->name) ?></span>
                        <?php endforeach; ?>

                        <span class="rec__meta">
                            <?= View::e($r->frequency->label()) ?>
                            <?php if (!$r->isActive): ?>
                                · <strong>suspendue</strong>
                            <?php elseif ($r->isFinished()): ?>
                                · terminée
                            <?php elseif ($next !== null): ?>
                                · prochaine le <?= View::e(Period::formatLongDate($next)) ?>
                            <?php endif; ?>
                            <?php if ($r->endsOn !== null): ?>
                                · jusqu'au <?= View::e($r->endsOn->format('d/m/Y')) ?>
                            <?php endif; ?>
                        </span>
                    </div>

                    <span class="rec__amount amount <?= $r->isExpense() ? 'amount--negative' : 'amount--positive' ?>">
                        <?= View::e(Money::formatSigned($r->amountCents)) ?>
                    </span>

                    <span class="rec__actions">
                        <a class="btn btn--quiet btn--small" href="/recurrences/<?= (int) $r->id ?>/modifier">Modifier</a>

                        <form method="post" action="/recurrences/<?= (int) $r->id ?>/basculer" class="inline-form">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn--quiet btn--small">
                                <?= $r->isActive ? 'Suspendre' : 'Réactiver' ?>
                            </button>
                        </form>

                        <form
                            method="post"
                            action="/recurrences/<?= (int) $r->id ?>/supprimer"
                            class="inline-form"
                            onsubmit="return confirm('Supprimer cette récurrence ? Les opérations déjà enregistrées seront conservées.');">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn--quiet btn--small btn--danger">Supprimer</button>
                        </form>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
