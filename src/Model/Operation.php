<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;

/**
 * Un mouvement sur les comptes.
 *
 * Le montant est un entier de centimes signe : negatif pour une depense,
 * positif pour une recette. Aucun flottant n'intervient, a aucun moment.
 */
final class Operation
{
    /**
     * @param array<int, Tag> $tags
     */
    public function __construct(
        public readonly int $id,
        public readonly string $label,
        public readonly int $amountCents,
        public readonly DateTimeImmutable $occurredOn,
        public readonly ?string $note,
        public readonly array $tags = [],
    ) {
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, Tag>      $tags
     */
    public static function fromRow(array $row, array $tags = []): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['label'],
            (int) $row['amount_cents'],
            new DateTimeImmutable((string) $row['occurred_on']),
            $row['note'] !== null && $row['note'] !== '' ? (string) $row['note'] : null,
            $tags,
        );
    }

    public function isExpense(): bool
    {
        return $this->amountCents < 0;
    }

    public function isIncome(): bool
    {
        return $this->amountCents > 0;
    }

    /**
     * @return array<int, int>
     */
    public function tagIds(): array
    {
        return array_map(static fn (Tag $tag): int => $tag->id, $this->tags);
    }
}
