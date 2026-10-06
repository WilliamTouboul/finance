<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;

/**
 * Calcul des echeances d'une operation recurrente.
 *
 * Volontairement sans etat et sans acces a la base : le calendrier se teste
 * alors sur ses seuls cas limites, qui sont nombreux -- fins de mois, annees
 * bissextiles, echeances au 31.
 *
 * La date de depart porte a elle seule le jour retenu. Un loyer qui commence
 * le 5 tombe le 5 de chaque mois ; inutile de stocker ce 5 dans une colonne
 * separee, qui pourrait finir par contredire la date de depart.
 */
final class Schedule
{
    /**
     * Garde-fou contre une recurrence dont la date de depart serait tres
     * ancienne : on ne remonte pas au-dela, pour ne pas proposer d'un coup des
     * centaines d'echeances a valider.
     */
    public const MAX_OCCURRENCES = 400;

    /**
     * Echeance numero $index, la numero 0 etant la date de depart elle-meme.
     *
     * Le decalage se calcule toujours depuis le premier jour du mois cible,
     * puis le jour est replace a la fin. Passer directement par
     * "+1 month" depuis le 31 janvier donnerait le 3 mars : PHP ajoute un mois
     * au numero de mois, puis reporte le debordement des jours. En partant du
     * premier du mois, il n'y a pas de debordement possible.
     */
    public static function occurrenceAt(DateTimeImmutable $start, Frequency $frequency, int $index): DateTimeImmutable
    {
        $start = $start->setTime(0, 0);

        if ($frequency === Frequency::Weekly) {
            return $start->modify('+' . (7 * $index) . ' days');
        }

        $months = (int) $frequency->monthStep() * $index;

        $firstOfTargetMonth = $start
            ->modify('first day of this month')
            ->modify(($months >= 0 ? '+' : '-') . abs($months) . ' months');

        // Une echeance au 31 tombe le 30, le 29 ou le 28 selon le mois.
        // On retient le dernier jour disponible plutot que de deborder sur le
        // mois suivant : un loyer "fin de mois" reste en fin de mois.
        $day = min(
            (int) $start->format('j'),
            (int) $firstOfTargetMonth->format('t')
        );

        return $firstOfTargetMonth->setDate(
            (int) $firstOfTargetMonth->format('Y'),
            (int) $firstOfTargetMonth->format('n'),
            $day
        );
    }

    /**
     * Toutes les echeances tombant jusqu'a une date donnee, incluse.
     *
     * @param DateTimeImmutable|null $endsOn fin de la recurrence, si elle en a une.
     * @return array<int, DateTimeImmutable>
     */
    public static function occurrencesUntil(
        DateTimeImmutable $start,
        Frequency $frequency,
        DateTimeImmutable $until,
        ?DateTimeImmutable $endsOn = null,
    ): array {
        $until = $until->setTime(0, 0);

        // Une recurrence qui se termine avant la date demandee borne la serie.
        if ($endsOn !== null) {
            $endsOn = $endsOn->setTime(0, 0);

            if ($endsOn < $until) {
                $until = $endsOn;
            }
        }

        $occurrences = [];

        for ($index = 0; $index < self::MAX_OCCURRENCES; $index++) {
            $date = self::occurrenceAt($start, $frequency, $index);

            if ($date > $until) {
                break;
            }

            $occurrences[] = $date;
        }

        return $occurrences;
    }

    /**
     * Prochaine echeance strictement posterieure a une date.
     *
     * Retourne null quand la recurrence est arrivee a son terme.
     */
    public static function nextAfter(
        DateTimeImmutable $start,
        Frequency $frequency,
        DateTimeImmutable $after,
        ?DateTimeImmutable $endsOn = null,
    ): ?DateTimeImmutable {
        $after = $after->setTime(0, 0);

        for ($index = 0; $index < self::MAX_OCCURRENCES; $index++) {
            $date = self::occurrenceAt($start, $frequency, $index);

            if ($date > $after) {
                return $endsOn !== null && $date > $endsOn->setTime(0, 0) ? null : $date;
            }
        }

        return null;
    }
}
