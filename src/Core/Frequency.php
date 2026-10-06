<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Rythme d'une operation recurrente.
 *
 * Les valeurs correspondent a l'ENUM de la colonne recurrences.frequency.
 */
enum Frequency: string
{
    case Weekly    = 'weekly';
    case Monthly   = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly    = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Weekly    => 'Chaque semaine',
            self::Monthly   => 'Chaque mois',
            self::Quarterly => 'Chaque trimestre',
            self::Yearly    => 'Chaque année',
        };
    }

    /**
     * Nombre de mois entre deux echeances, ou null pour un rythme hebdomadaire
     * qui ne se compte pas en mois.
     */
    public function monthStep(): ?int
    {
        return match ($this) {
            self::Weekly    => null,
            self::Monthly   => 1,
            self::Quarterly => 3,
            self::Yearly    => 12,
        };
    }

    public static function tryFromString(?string $value): ?self
    {
        return $value === null ? null : self::tryFrom($value);
    }
}
