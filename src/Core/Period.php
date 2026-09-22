<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;

/**
 * Une periode consultee : un jour, un mois ou une annee.
 *
 * Objet-valeur immuable. Il expose deux bornes de dates inclusives, et c'est
 * tout ce que les requetes SQL ont besoin de connaitre. Aucune requete ne doit
 * filtrer avec YEAR(occurred_on) ou MONTH(occurred_on) : appliquer une
 * fonction sur une colonne indexee empeche MySQL d'utiliser son index et le
 * force a parcourir toute la table. Des bornes de plage, elles, exploitent
 * l'index (user_id, occurred_on, id).
 */
final class Period
{
    /**
     * Noms de mois en francais.
     *
     * Ecrits a la main plutot que via IntlDateFormatter : l'extension intl
     * n'est pas garantie sur un hebergement mutualise, et douze libelles ne
     * justifient pas une dependance qui pourrait manquer en production.
     *
     * @var array<int, string>
     */
    private const MONTHS = [
        1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
    ];

    /**
     * @var array<int, string>
     */
    private const WEEKDAYS = [
        1 => 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche',
    ];

    private function __construct(
        public readonly Granularity $granularity,
        public readonly DateTimeImmutable $start,
        public readonly DateTimeImmutable $end,
    ) {
    }

    /**
     * "15 septembre 2026". Sert partout ou une date est affichee seule.
     */
    public static function formatLongDate(DateTimeImmutable $date): string
    {
        return $date->format('j') . ' '
            . self::MONTHS[(int) $date->format('n')] . ' '
            . $date->format('Y');
    }

    /**
     * "lundi 15 septembre 2026", pour les en-tetes de regroupement par jour.
     */
    public static function formatDayHeading(DateTimeImmutable $date): string
    {
        return self::WEEKDAYS[(int) $date->format('N')] . ' ' . self::formatLongDate($date);
    }

    public static function day(DateTimeImmutable $date): self
    {
        $start = $date->setTime(0, 0);

        return new self(Granularity::Day, $start, $start);
    }

    public static function month(int $year, int $month): self
    {
        $start = (new DateTimeImmutable())->setDate($year, $month, 1)->setTime(0, 0);

        return new self(Granularity::Month, $start, $start->modify('last day of this month'));
    }

    public static function year(int $year): self
    {
        $start = (new DateTimeImmutable())->setDate($year, 1, 1)->setTime(0, 0);

        return new self(Granularity::Year, $start, $start->setDate($year, 12, 31));
    }

    public static function currentMonth(): self
    {
        $now = new DateTimeImmutable();

        return self::month((int) $now->format('Y'), (int) $now->format('n'));
    }

    /**
     * Construit la periode depuis le parametre d'URL.
     *
     * La granularite se deduit du format, ce qui evite un second parametre :
     *   2026        -> annee
     *   2026-09     -> mois
     *   2026-09-15  -> jour
     *
     * Toute valeur invalide retombe silencieusement sur le mois courant : une
     * URL trafiquee ne doit pas produire d'erreur, juste une vue par defaut.
     */
    public static function fromParam(?string $value): self
    {
        if ($value === null || $value === '') {
            return self::currentMonth();
        }

        if (preg_match('/^(\d{4})$/', $value, $m) === 1) {
            return self::year(self::clampYear((int) $m[1]));
        }

        if (preg_match('/^(\d{4})-(\d{2})$/', $value, $m) === 1) {
            $month = (int) $m[2];

            return $month >= 1 && $month <= 12
                ? self::month(self::clampYear((int) $m[1]), $month)
                : self::currentMonth();
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

            // createFromFormat accepte des dates inexistantes comme le 31 juin
            // en les reportant au mois suivant : on verifie donc que la date
            // rendue correspond bien a la chaine demandee.
            return $date !== false && $date->format('Y-m-d') === $value
                ? self::day($date)
                : self::currentMonth();
        }

        return self::currentMonth();
    }

    /**
     * Valeur a placer dans les URL.
     */
    public function toParam(): string
    {
        return match ($this->granularity) {
            Granularity::Day   => $this->start->format('Y-m-d'),
            Granularity::Month => $this->start->format('Y-m'),
            Granularity::Year  => $this->start->format('Y'),
        };
    }

    /**
     * Libelle affiche : "15 septembre 2026", "septembre 2026", "2026".
     */
    public function label(): string
    {
        $month = self::MONTHS[(int) $this->start->format('n')];

        return match ($this->granularity) {
            Granularity::Day   => $this->start->format('j') . ' ' . $month . ' ' . $this->start->format('Y'),
            Granularity::Month => $month . ' ' . $this->start->format('Y'),
            Granularity::Year  => $this->start->format('Y'),
        };
    }

    public function previous(): self
    {
        return $this->shift(-1);
    }

    public function next(): self
    {
        return $this->shift(1);
    }

    /**
     * Meme instant, vu a une autre echelle. Sert aux onglets Jour / Mois / Annee.
     */
    public function withGranularity(Granularity $granularity): self
    {
        return match ($granularity) {
            Granularity::Day   => self::day($this->start),
            Granularity::Month => self::month(
                (int) $this->start->format('Y'),
                (int) $this->start->format('n'),
            ),
            Granularity::Year  => self::year((int) $this->start->format('Y')),
        };
    }

    /**
     * La periode contient-elle aujourd'hui ? Utilise pour desactiver la
     * fleche "suivant" plutot que de laisser naviguer indefiniment dans le futur.
     */
    public function isCurrent(): bool
    {
        $today = (new DateTimeImmutable())->format('Y-m-d');

        return $today >= $this->startSql() && $today <= $this->endSql();
    }

    public function startSql(): string
    {
        return $this->start->format('Y-m-d');
    }

    public function endSql(): string
    {
        return $this->end->format('Y-m-d');
    }

    /**
     * Decale la periode d'un cran, en avant ou en arriere.
     *
     * Le calcul part toujours du premier jour de la periode. Se decaler depuis
     * une date quelconque donnerait des resultats faux en fin de mois : le
     * 31 mars moins un mois vaut le 3 mars en PHP, parce que fevrier est plus court.
     */
    private function shift(int $direction): self
    {
        $step = $direction < 0 ? '-1 ' : '+1 ';

        return match ($this->granularity) {
            Granularity::Day   => self::day($this->start->modify($step . 'day')),
            Granularity::Month => self::month(
                (int) $this->start->modify($step . 'month')->format('Y'),
                (int) $this->start->modify($step . 'month')->format('n'),
            ),
            Granularity::Year  => self::year((int) $this->start->format('Y') + $direction),
        };
    }

    /**
     * Borne les annees a un intervalle plausible : evite qu'une URL forgee
     * demande l'an 9999 et fasse calculer des agregats inutiles.
     */
    private static function clampYear(int $year): int
    {
        return max(2000, min(2100, $year));
    }
}
