<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\Period;
use App\Model\Operation;
use App\Model\Tag;
use DateTimeImmutable;
use PDO;

/**
 * Seul point d'acces aux tables operations et operation_tag.
 *
 * Comme pour les tags, l'identifiant d'utilisateur figure dans chaque clause
 * WHERE, y compris pour un acces par identifiant.
 */
final class OperationRepository
{
    private const COLUMNS = 'id, label, amount_cents, occurred_on, note';

    /**
     * Les operations d'une periode, de la plus recente a la plus ancienne.
     *
     * Le filtre porte sur des bornes de dates et jamais sur YEAR() ou MONTH() :
     * une fonction appliquee a occurred_on empecherait MySQL de se servir de
     * l'index (user_id, occurred_on, id) et le forcerait a lire toute la table.
     *
     * @return array<int, Operation>
     */
    public function listForPeriod(int $userId, Period $period, ?int $tagId = null): array
    {
        $params = [];
        $join   = '';

        // Le filtre par tag passe par une jointure sur la table de liaison.
        // Son marqueur est ajoute EN PREMIER : les parametres positionnels se
        // lient dans l'ordre d'apparition dans la requete, et le JOIN precede
        // le WHERE. Les ajouter dans l'ordre des arguments les decalerait tous.
        if ($tagId !== null) {
            $join     = ' JOIN operation_tag ot ON ot.operation_id = o.id AND ot.tag_id = ?';
            $params[] = $tagId;
        }

        $params[] = $userId;
        $params[] = $period->startSql();
        $params[] = $period->endSql();

        $rows = Database::all(
            'SELECT o.id, o.label, o.amount_cents, o.occurred_on, o.note
               FROM operations o' . $join . '
              WHERE o.user_id = ?
                AND o.occurred_on BETWEEN ? AND ?
              ORDER BY o.occurred_on DESC, o.id DESC',
            $params
        );

        return $this->hydrateWithTags($rows);
    }

    /**
     * Les dernieres operations saisies, toutes periodes confondues.
     *
     * @return array<int, Operation>
     */
    public function recent(int $userId, int $limit = 10): array
    {
        // LIMIT n'accepte pas de parametre prepare sous MySQL : la valeur est
        // forcee en entier puis bornee avant d'etre interpolee.
        $limit = max(1, min(100, $limit));

        $rows = Database::all(
            'SELECT ' . self::COLUMNS . '
               FROM operations
              WHERE user_id = ?
              ORDER BY occurred_on DESC, id DESC
              LIMIT ' . $limit,
            [$userId]
        );

        return $this->hydrateWithTags($rows);
    }

    public function find(int $id, int $userId): ?Operation
    {
        $row = Database::one(
            'SELECT ' . self::COLUMNS . ' FROM operations WHERE id = ? AND user_id = ? LIMIT 1',
            [$id, $userId]
        );

        if ($row === null) {
            return null;
        }

        return Operation::fromRow($row, $this->tagsFor([$id])[$id] ?? []);
    }

    /**
     * Cree une operation et ses liaisons de tags.
     *
     * Le tout dans une transaction : une operation enregistree sans ses tags,
     * ou des liaisons pointant vers une operation absente, laisserait des
     * donnees incoherentes. Ici la transaction fonctionne bien -- ce sont des
     * INSERT, pas du DDL, qui lui declencherait un commit implicite.
     *
     * @param array<int, int> $tagIds identifiants deja valides comme appartenant a l'utilisateur.
     */
    public function create(
        int $userId,
        string $label,
        int $amountCents,
        DateTimeImmutable $occurredOn,
        ?string $note,
        array $tagIds,
    ): int {
        return (int) Database::transaction(function (PDO $pdo) use ($userId, $label, $amountCents, $occurredOn, $note, $tagIds): int {
            $statement = $pdo->prepare(
                'INSERT INTO operations (user_id, label, amount_cents, occurred_on, note)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $statement->execute([$userId, $label, $amountCents, $occurredOn->format('Y-m-d'), $note]);

            $operationId = (int) $pdo->lastInsertId();

            $this->linkTags($pdo, $operationId, $tagIds);

            return $operationId;
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
        DateTimeImmutable $occurredOn,
        ?string $note,
        array $tagIds,
    ): void {
        Database::transaction(function (PDO $pdo) use ($id, $userId, $label, $amountCents, $occurredOn, $note, $tagIds): void {
            $statement = $pdo->prepare(
                'UPDATE operations
                    SET label = ?, amount_cents = ?, occurred_on = ?, note = ?
                  WHERE id = ? AND user_id = ?'
            );
            $statement->execute([$label, $amountCents, $occurredOn->format('Y-m-d'), $note, $id, $userId]);

            // Les liaisons sont remplacees en bloc plutot que comparees une a
            // une : sur une poignee de tags, la difference de cout est nulle et
            // le code reste evident.
            $pdo->prepare('DELETE FROM operation_tag WHERE operation_id = ?')->execute([$id]);

            $this->linkTags($pdo, $id, $tagIds);
        });
    }

    public function delete(int $id, int $userId): void
    {
        // Les liaisons partent avec, via ON DELETE CASCADE.
        Database::run('DELETE FROM operations WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    /**
     * Solde cumule a une date donnee, bornes incluses.
     *
     * C'est le solde affiche sur le tableau de bord : "ou j'en etais a la fin
     * de la periode consultee", donc la somme de tout l'historique jusqu'a
     * cette date et non le seul solde de la periode.
     */
    public function balanceAsOf(int $userId, string $dateSql): int
    {
        return (int) Database::value(
            'SELECT COALESCE(SUM(amount_cents), 0)
               FROM operations
              WHERE user_id = ? AND occurred_on <= ?',
            [$userId, $dateSql]
        );
    }

    /**
     * Entrees et sorties de la periode.
     *
     * Les deux totaux sont calcules en une seule passe avec des CASE plutot
     * qu'en deux requetes : la table n'est parcourue qu'une fois.
     *
     * @return array{income: int, expense: int}
     */
    public function totalsForPeriod(int $userId, Period $period): array
    {
        $row = Database::one(
            'SELECT
                COALESCE(SUM(CASE WHEN amount_cents > 0 THEN amount_cents ELSE 0 END), 0) AS income,
                COALESCE(SUM(CASE WHEN amount_cents < 0 THEN amount_cents ELSE 0 END), 0) AS expense
               FROM operations
              WHERE user_id = ? AND occurred_on BETWEEN ? AND ?',
            [$userId, $period->startSql(), $period->endSql()]
        );

        return [
            'income'  => (int) ($row['income'] ?? 0),
            // Deja negatif en base : on le garde tel quel pour rester coherent
            // avec la convention du projet.
            'expense' => (int) ($row['expense'] ?? 0),
        ];
    }

    public function countForPeriod(int $userId, Period $period): int
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM operations
              WHERE user_id = ? AND occurred_on BETWEEN ? AND ?',
            [$userId, $period->startSql(), $period->endSql()]
        );
    }

    /**
     * Annee de la toute premiere operation, pour borner le selecteur d'annee.
     */
    public function firstYear(int $userId): ?int
    {
        $value = Database::value('SELECT MIN(occurred_on) FROM operations WHERE user_id = ?', [$userId]);

        return is_string($value) ? (int) substr($value, 0, 4) : null;
    }

    /**
     * Construit les objets Operation en leur attachant leurs tags.
     *
     * Deux requetes en tout, quel que soit le nombre d'operations. Charger les
     * tags operation par operation serait le probleme dit "N+1" : cent lignes
     * affichees declencheraient cent-une requetes.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, Operation>
     */
    private function hydrateWithTags(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $tagsByOperation = $this->tagsFor(array_map(static fn (array $row): int => (int) $row['id'], $rows));

        return array_map(
            static fn (array $row): Operation => Operation::fromRow(
                $row,
                $tagsByOperation[(int) $row['id']] ?? []
            ),
            $rows
        );
    }

    /**
     * Tags de plusieurs operations, regroupes par operation.
     *
     * @param array<int, int> $operationIds
     * @return array<int, array<int, Tag>>
     */
    private function tagsFor(array $operationIds): array
    {
        if ($operationIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($operationIds), '?'));

        $rows = Database::all(
            "SELECT ot.operation_id, t.id, t.name, t.color
               FROM operation_tag ot
               JOIN tags t ON t.id = ot.tag_id
              WHERE ot.operation_id IN ({$placeholders})
              ORDER BY t.name",
            $operationIds
        );

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['operation_id']][] = Tag::fromRow($row);
        }

        return $grouped;
    }

    /**
     * @param array<int, int> $tagIds
     */
    private function linkTags(PDO $pdo, int $operationId, array $tagIds): void
    {
        if ($tagIds === []) {
            return;
        }

        $statement = $pdo->prepare(
            'INSERT INTO operation_tag (operation_id, tag_id) VALUES (?, ?)'
        );

        foreach (array_unique($tagIds) as $tagId) {
            $statement->execute([$operationId, $tagId]);
        }
    }
}
