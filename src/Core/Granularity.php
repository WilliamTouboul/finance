<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Niveau de decoupage temporel du tableau de bord.
 *
 * La valeur de chaque cas sert aussi de libelle dans les URL.
 */
enum Granularity: string
{
    case Day   = 'jour';
    case Month = 'mois';
    case Year  = 'annee';

    public function label(): string
    {
        return match ($this) {
            self::Day   => 'Jour',
            self::Month => 'Mois',
            self::Year  => 'Année',
        };
    }
}
