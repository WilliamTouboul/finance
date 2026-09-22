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
     * @param int|null        $primaryTagId tag portant le montant dans les
     *                                      repartitions par pole. Null quand
     *                                      l'operation n'a aucun tag.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $label,
        public readonly int $amountCents,
        public readonly DateTimeImmutable $occurredOn,
        public readonly ?string $note,
        public readonly array $tags = [],
        public readonly ?int $primaryTagId = null,
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
            isset($row['primary_tag_id']) && $row['primary_tag_id'] !== null
                ? (int) $row['primary_tag_id']
                : null,
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
     * Le tag qui porte le montant, s'il est connu.
     */
    public function primaryTag(): ?Tag
    {
        foreach ($this->tags as $tag) {
            if ($tag->id === $this->primaryTagId) {
                return $tag;
            }
        }

        return null;
    }

    /**
     * Les tags de l'operation, le principal en premier.
     *
     * L'ordre porte une information : il evite d'avoir a marquer le tag
     * principal d'un signe distinctif dans les listes, ou la place suffit.
     *
     * @return array<int, Tag>
     */
    public function tagsPrimaryFirst(): array
    {
        $primary = $this->primaryTag();

        if ($primary === null) {
            return $this->tags;
        }

        $others = array_filter($this->tags, static fn (Tag $t): bool => $t->id !== $primary->id);

        return [$primary, ...array_values($others)];
    }

    /**
     * @return array<int, int>
     */
    public function tagIds(): array
    {
        return array_map(static fn (Tag $tag): int => $tag->id, $this->tags);
    }
}
