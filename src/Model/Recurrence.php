<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Frequency;
use App\Core\Schedule;
use DateTimeImmutable;

/**
 * Modele d'operation qui revient a intervalle regulier.
 *
 * Une recurrence ne cree rien d'elle-meme : elle decrit ce qui est attendu et
 * a quel rythme. Les operations naissent d'une validation explicite, ce qui
 * evite de voir apparaitre dans ses comptes des ecritures qu'on n'a pas vues
 * passer -- et permet d'ajuster un montant variable au moment de la saisie.
 */
final class Recurrence
{
    /**
     * @param array<int, Tag> $tags
     */
    public function __construct(
        public readonly int $id,
        public readonly string $label,
        public readonly int $amountCents,
        public readonly Frequency $frequency,
        public readonly DateTimeImmutable $startsOn,
        public readonly ?DateTimeImmutable $endsOn,
        public readonly ?string $note,
        public readonly array $tags = [],
        public readonly ?int $primaryTagId = null,
        public readonly bool $isActive = true,
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
            Frequency::from((string) $row['frequency']),
            new DateTimeImmutable((string) $row['starts_on']),
            $row['ends_on'] !== null ? new DateTimeImmutable((string) $row['ends_on']) : null,
            $row['note'] !== null && $row['note'] !== '' ? (string) $row['note'] : null,
            $tags,
            $row['primary_tag_id'] !== null ? (int) $row['primary_tag_id'] : null,
            (bool) $row['is_active'],
        );
    }

    public function isExpense(): bool
    {
        return $this->amountCents < 0;
    }

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

    /**
     * Prochaine echeance a venir, ou null si la recurrence est terminee.
     */
    public function nextOccurrence(?DateTimeImmutable $after = null): ?DateTimeImmutable
    {
        if (!$this->isActive) {
            return null;
        }

        return Schedule::nextAfter(
            $this->startsOn,
            $this->frequency,
            $after ?? new DateTimeImmutable('today'),
            $this->endsOn,
        );
    }

    /**
     * La recurrence est-elle arrivee a son terme ?
     */
    public function isFinished(?DateTimeImmutable $on = null): bool
    {
        if ($this->endsOn === null) {
            return false;
        }

        return ($on ?? new DateTimeImmutable('today'))->setTime(0, 0) > $this->endsOn;
    }
}
