<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Plafond de depense mensuel affecte a un pole.
 *
 * La consommation se calcule sur le **tag principal** des operations, par
 * coherence avec le camembert : compter une operation dans chacun de ses tags
 * consommerait plusieurs budgets a la fois pour une seule depense.
 *
 * Un depassement est une information, pas une faute : rien n'est bloque, la
 * barre change simplement de couleur.
 */
final class Budget
{
    /** Part a partir de laquelle on previent que le plafond approche. */
    public const WARNING_THRESHOLD = 0.8;

    public function __construct(
        public readonly int $id,
        public readonly Tag $tag,
        /** Plafond mensuel, toujours positif. */
        public readonly int $amountCents,
        /** Depense constatee sur la periode, en valeur absolue. */
        public readonly int $spentCents = 0,
        /**
         * Nombre de mois couverts par la periode consultee. Le plafond est
         * multiplie d'autant : sur une annee, un budget de 300 euros par mois
         * se compare a 3 600 euros de depenses.
         */
        public readonly int $monthsCovered = 1,
    ) {
    }

    /**
     * Plafond ramene a la periode consultee.
     */
    public function ceilingCents(): int
    {
        return $this->amountCents * max(1, $this->monthsCovered);
    }

    public function remainingCents(): int
    {
        return $this->ceilingCents() - $this->spentCents;
    }

    /**
     * Part du plafond consommee. Peut depasser 1.
     */
    public function share(): float
    {
        $ceiling = $this->ceilingCents();

        return $ceiling <= 0 ? 0.0 : $this->spentCents / $ceiling;
    }

    /**
     * Part affichable par la barre de progression, plafonnee a 100 %.
     * Au-dela, c'est la couleur qui porte l'information.
     */
    public function barShare(): float
    {
        return min(1.0, $this->share());
    }

    public function isExceeded(): bool
    {
        return $this->spentCents > $this->ceilingCents();
    }

    /**
     * Etat du budget, utilise comme suffixe de classe CSS.
     */
    public function level(): string
    {
        if ($this->isExceeded()) {
            return 'exceeded';
        }

        return $this->share() >= self::WARNING_THRESHOLD ? 'warning' : 'ok';
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row, int $monthsCovered = 1): self
    {
        return new self(
            (int) $row['id'],
            new Tag(
                (int) $row['tag_id'],
                (string) $row['tag_name'],
                (string) $row['tag_color'],
            ),
            (int) $row['amount_cents'],
            (int) ($row['spent'] ?? 0),
            $monthsCovered,
        );
    }
}
