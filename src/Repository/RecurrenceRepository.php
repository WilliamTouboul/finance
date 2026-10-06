<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\Frequency;
use App\Core\Schedule;
use App\Model\Recurrence;
use App\Model\Tag;
use DateTimeImmutable;
use PDO;

/**
 * Seul point d'acces aux tables recurrences et recurrence_tag.
 *
 * Comme partout ailleurs, l'identifiant d'utilisateur figure dans chaque
 * clause WHERE, y compris pour un acces par identifiant.
 */
final class RecurrenceRepository
{
    private const COLUMNS = 'id, label, amount_cents, frequency, starts_on, ends_on, note, primary_tag_id, is_active';

    /**
     * @return array<int, Recurrence>
     */
    public function allForUser(int $userId): array
    {
        $rows = Database::all(
            'SELECT ' . self::COLUMNS . '
               FROM recurrences
              WHERE user_id = ?
              ORDER BY is_active DESC, label',
            [$userId]
        );

        return $this->hydrateWithTags($rows);
    }

    public function find(int $id, int $userId): ?Recurrence
    {
        $row = Database::one(
            'SELECT ' . self::COLUMNS . ' FROM recurrences WHERE id = ? AND user_id = ? LIMIT 1',
            [$id, $userId]
        );

        if ($row === null) {
            return null;
        }

        return Recurrence::fromRow($row, $this->tagsFor([$id])[$id] ?? []);
    }

    /**
     * Echeances arrivees a terme et pas encore transformees en operation.
     *
     * Rien n'est ecrit ici : cette methode ne fait que comparer ce qui etait
     * attendu a ce qui existe. La validation reste a l'utilisateur.
     *
     * Les dates deja materialisees sont chargees en une seule requete pour
     * toutes les recurrences, puis recoupees en memoire : interroger la base
     * echeance par echeance produirait des dizaines d'allers-retours.
     *
     * @return array<int, array{recurrence: Recurrence, date: DateTimeImmutable}>
     */
    public function pendingOccurrences(int $userId, ?DateTimeImmutable $until = null): array
    {
        $until = ($until ?? new DateTimeImmutable('today'))->setTime(0, 0);

        $recurrences = array_filter(
            $this->allForUser($userId),
            static fn (Recurrence $r): bool => $r->isActive
        );

        if ($recurrences === []) {
            return [];
        }

        $materialised = $this->materialisedDates($userId);
        $pending      = [];

        foreach ($recurrences as $recurrence) {
            $already = $materialised[$recurrence->id] ?? [];

            foreach (Schedule::occurrencesUntil(
                $recurrence->startsOn,
                $recurrence->frequency,
                $until,
                $recurrence->endsOn
            ) as $date) {
                if (!in_array($date->format('Y-m-d'), $already, true)) {
                    $pending[] = ['recurrence' => $recurrence, 'date' => $date];
                }
            }
        }

        // De la plus ancienne a la plus recente : on valide dans l'ordre ou les
        // echeances sont tombees.
        usort($pending, static fn (array $a, array $b): int => $a['date'] <=> $b['date']);

        return $pending;
    }

    public function countPending(int $userId): int
    {
        return count($this->pendingOccurrences($userId));
    }

    /**
     * @param array<int, int> $tagIds
     */
    public function create(
        int $userId,
        string $label,
        int $amountCents,
        Frequency $frequency,
        DateTimeImmutable $startsOn,
        ?DateTimeImmutable $endsOn,
        ?string $note,
        array $tagIds,
        ?int $primaryTagId = null,
    ): int {
        return (int) Database::transaction(function (PDO $pdo) use ($userId, $label, $amountCents, $frequency, $startsOn, $endsOn, $note, $tagIds, $primaryTagId): int {
            $pdo->prepare(
                'INSERT INTO recurrences
                        (user_id, label, amount_cents, frequency, starts_on, ends_on, note, primary_tag_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $userId,
                $label,
                $amountCents,
                $frequency->value,
                $startsOn->format('Y-m-d'),
                $endsOn?->format('Y-m-d'),
                $note,
                self::resolvePrimaryTag($tagIds, $primaryTagId),
            ]);

            $id = (int) $pdo->lastInsertId();
            $this->linkTags($pdo, $id, $tagIds);

            return $id;
        });
    }

    /**
     * @param array<int, int> $tagIds
     */
    public function update(
        int $id,
        int $userId,
        string $label,
        int $amountCents,
        Frequency $frequency,
        DateTimeImmutable $startsOn,
        ?DateTimeImmutable $endsOn,
        ?string $note,
        array $tagIds,
        ?int $primaryTagId = null,
    ): void {
        Database::transaction(function (PDO $pdo) use ($id, $userId, $label, $amountCents, $frequency, $startsOn, $endsOn, $note, $tagIds, $primaryTagId): void {
            $pdo->prepare(
                'UPDATE recurrences
                    SET label = ?, amount_cents = ?, frequency = ?, starts_on = ?,
                        ends_on = ?, note = ?, primary_tag_id = ?
                  WHERE id = ? AND user_id = ?'
            )->execute([
                $label,
                $amountCents,
                $frequency->value,
                $startsOn->format('Y-m-d'),
                $endsOn?->format('Y-m-d'),
                $note,
                self::resolvePrimaryTag($tagIds, $primaryTagId),
                $id,
                $userId,
            ]);

            $pdo->prepare('DELETE FROM recurrence_tag WHERE recurrence_id = ?')->execute([$id]);
            $this->linkTags($pdo, $id, $tagIds);
        });
    }

    public function setActive(int $id, int $userId, bool $active): void
    {
        Database::run(
            'UPDATE recurrences SET is_active = ? WHERE id = ? AND user_id = ?',
            [$active ? 1 : 0, $id, $userId]
        );
    }

    /**
     * Les operations deja generees survivent, par la contrainte
     * ON DELETE SET NULL : elles ont reellement eu lieu.
     */
    public function delete(int $id, int $userId): void
    {
        Database::run('DELETE FROM recurrences WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    /**
     * Dates deja transformees en operation, par recurrence.
     *
     * @return array<int, array<int, string>>
     */
    private function materialisedDates(int $userId): array
    {
        $rows = Database::all(
            'SELECT recurrence_id, occurred_on
               FROM operations
              WHERE user_id = ? AND recurrence_id IS NOT NULL',
            [$userId]
        );

        $byRecurrence = [];
        foreach ($rows as $row) {
            $byRecurrence[(int) $row['recurrence_id']][] = (string) $row['occurred_on'];
        }

        return $byRecurrence;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, Recurrence>
     */
    private function hydrateWithTags(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $tagsByRecurrence = $this->tagsFor(
            array_map(static fn (array $row): int => (int) $row['id'], $rows)
        );

        return array_map(
            static fn (array $row): Recurrence => Recurrence::fromRow(
                $row,
                $tagsByRecurrence[(int) $row['id']] ?? []
            ),
            $rows
        );
    }

    /**
     * @param array<int, int> $recurrenceIds
     * @return array<int, array<int, Tag>>
     */
    private function tagsFor(array $recurrenceIds): array
    {
        if ($recurrenceIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($recurrenceIds), '?'));

        $rows = Database::all(
            "SELECT rt.recurrence_id, t.id, t.name, t.color
               FROM recurrence_tag rt
               JOIN tags t ON t.id = rt.tag_id
              WHERE rt.recurrence_id IN ({$placeholders})
              ORDER BY t.name",
            $recurrenceIds
        );

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['recurrence_id']][] = Tag::fromRow($row);
        }

        return $grouped;
    }

    /**
     * @param array<int, int> $tagIds
     */
    private static function resolvePrimaryTag(array $tagIds, ?int $primaryTagId): ?int
    {
        if ($tagIds === []) {
            return null;
        }

        if ($primaryTagId !== null && in_array($primaryTagId, $tagIds, true)) {
            return $primaryTagId;
        }

        return $tagIds[0];
    }

    /**
     * @param array<int, int> $tagIds
     */
    private function linkTags(PDO $pdo, int $recurrenceId, array $tagIds): void
    {
        if ($tagIds === []) {
            return;
        }

        $statement = $pdo->prepare(
            'INSERT INTO recurrence_tag (recurrence_id, tag_id) VALUES (?, ?)'
        );

        foreach (array_unique($tagIds) as $tagId) {
            $statement->execute([$recurrenceId, $tagId]);
        }
    }
}
