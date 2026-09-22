<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\Period;
use App\Model\Operation;
use App\Model\OperationFilter;
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
    private const COLUMNS = 'id, label, amount_cents, occurred_on, note, primary_tag_id';

    /**
     * Les operations repondant aux criteres, de la plus recente a la plus ancienne.
     *
     * Le filtre de dates porte sur des bornes et jamais sur YEAR() ou MONTH() :
     * une fonction appliquee a occurred_on empecherait MySQL de se servir de
     * l'index (user_id, occurred_on, id) et le forcerait a lire toute la table.
     *
     * @return array<int, Operation>
     */
    public function findBy(int $userId, OperationFilter $filter): array
    {
        [$where, $params, $join] = $this->buildCriteria($userId, $filter);

        $rows = Database::all(
            'SELECT DISTINCT o.id, o.label, o.amount_cents, o.occurred_on, o.note, o.primary_tag_id
               FROM operations o' . $join . '
              WHERE ' . $where . '
              ORDER BY o.occurred_on DESC, o.id DESC',
            $params
        );

        return $this->hydrateWithTags($rows);
    }

    /**
     * Entrees et sorties pour les memes criteres.
     *
     * Les deux totaux sont calcules en une passe avec des CASE plutot qu'en
     * deux requetes : la table n'est parcourue qu'une fois.
     *
     * @return array{income: int, expense: int}
     */
    public function totalsFor(int $userId, OperationFilter $filter): array
    {
        [$where, $params, $join] = $this->buildCriteria($userId, $filter);

        // DISTINCT indispensable : la jointure sur les tags duplique une
        // operation portant plusieurs des tags filtres, et son montant serait
        // alors compte autant de fois.
        $row = Database::one(
            'SELECT
                COALESCE(SUM(CASE WHEN t.amount_cents > 0 THEN t.amount_cents ELSE 0 END), 0) AS income,
                COALESCE(SUM(CASE WHEN t.amount_cents < 0 THEN t.amount_cents ELSE 0 END), 0) AS expense
               FROM (
                   SELECT DISTINCT o.id, o.amount_cents
                     FROM operations o' . $join . '
                    WHERE ' . $where . '
               ) AS t',
            $params
        );

        return [
            'income'  => (int) ($row['income'] ?? 0),
            'expense' => (int) ($row['expense'] ?? 0),
        ];
    }

    /**
     * Depenses de la periode reparties par tag principal.
     *
     * C'est la source du camembert. Seul le tag principal compte : sans cela,
     * une operation portant deux tags serait comptee dans chacun et le total
     * des parts depasserait les depenses reelles.
     *
     * LEFT JOIN : une depense sans tag principal doit apparaitre malgre tout,
     * regroupee sous "Non classe", sinon le camembert ne totaliserait pas les
     * depenses de la periode.
     *
     * @return array<int, array{tag: Tag|null, total: int}> montants positifs, tries decroissant.
     */
    public function expensesByPrimaryTag(int $userId, Period $period): array
    {
        $rows = Database::all(
            'SELECT t.id, t.name, t.color, SUM(-o.amount_cents) AS total
               FROM operations o
               LEFT JOIN tags t ON t.id = o.primary_tag_id
              WHERE o.user_id = ?
                AND o.occurred_on BETWEEN ? AND ?
                AND o.amount_cents < 0
              GROUP BY t.id, t.name, t.color
              ORDER BY total DESC',
            [$userId, $period->startSql(), $period->endSql()]
        );

        return array_map(
            static fn (array $row): array => [
                'tag'   => $row['id'] === null ? null : Tag::fromRow($row),
                'total' => (int) $row['total'],
            ],
            $rows
        );
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
        ?int $primaryTagId = null,
    ): int {
        return (int) Database::transaction(function (PDO $pdo) use ($userId, $label, $amountCents, $occurredOn, $note, $tagIds, $primaryTagId): int {
            $statement = $pdo->prepare(
                'INSERT INTO operations (user_id, label, amount_cents, occurred_on, note, primary_tag_id)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([
                $userId,
                $label,
                $amountCents,
                $occurredOn->format('Y-m-d'),
                $note,
                self::resolvePrimaryTag($tagIds, $primaryTagId),
            ]);

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
        ?int $primaryTagId = null,
    ): void {
        Database::transaction(function (PDO $pdo) use ($id, $userId, $label, $amountCents, $occurredOn, $note, $tagIds, $primaryTagId): void {
            $statement = $pdo->prepare(
                'UPDATE operations
                    SET label = ?, amount_cents = ?, occurred_on = ?, note = ?, primary_tag_id = ?
                  WHERE id = ? AND user_id = ?'
            );
            $statement->execute([
                $label,
                $amountCents,
                $occurredOn->format('Y-m-d'),
                $note,
                self::resolvePrimaryTag($tagIds, $primaryTagId),
                $id,
                $userId,
            ]);

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
     * Annee de la toute premiere operation, pour borner le selecteur d'annee.
     */
    public function firstYear(int $userId): ?int
    {
        $value = Database::value('SELECT MIN(occurred_on) FROM operations WHERE user_id = ?', [$userId]);

        return is_string($value) ? (int) substr($value, 0, 4) : null;
    }

    /**
     * Assemble la clause WHERE, ses parametres et la jointure eventuelle.
     *
     * Les parametres sont empiles dans l'ordre exact ou leurs marqueurs
     * apparaissent dans la requete : les parametres positionnels se lient par
     * position, et un JOIN precede toujours le WHERE. Les ranger dans l'ordre
     * des arguments de la methode les decalerait tous.
     *
     * @return array{0: string, 1: array<int, mixed>, 2: string}
     */
    private function buildCriteria(int $userId, OperationFilter $filter): array
    {
        $params = [];
        $join   = '';

        if ($filter->tagIds !== []) {
            $placeholders = implode(', ', array_fill(0, count($filter->tagIds), '?'));
            $join = " JOIN operation_tag ot ON ot.operation_id = o.id AND ot.tag_id IN ({$placeholders})";
            $params = array_merge($params, $filter->tagIds);
        }

        $where    = ['o.user_id = ?', 'o.occurred_on BETWEEN ? AND ?'];
        $params[] = $userId;
        $params[] = $filter->period->startSql();
        $params[] = $filter->period->endSql();

        if ($filter->search !== '') {
            $where[]  = '(o.label LIKE ? OR o.note LIKE ?)';
            // Les caracteres speciaux de LIKE sont neutralises : sans cela, un
            // "%" saisi dans la recherche ferait tout remonter.
            $needle   = '%' . self::escapeLike($filter->search) . '%';
            $params[] = $needle;
            $params[] = $needle;
        }

        if ($filter->direction === OperationFilter::DIRECTION_EXPENSE) {
            $where[] = 'o.amount_cents < 0';
        } elseif ($filter->direction === OperationFilter::DIRECTION_INCOME) {
            $where[] = 'o.amount_cents > 0';
        }

        // La fourchette porte sur la valeur absolue : l'utilisateur raisonne en
        // "entre 20 et 50 euros", sans se soucier du signe.
        if ($filter->minCents !== null) {
            $where[]  = 'ABS(o.amount_cents) >= ?';
            $params[] = $filter->minCents;
        }

        if ($filter->maxCents !== null) {
            $where[]  = 'ABS(o.amount_cents) <= ?';
            $params[] = $filter->maxCents;
        }

        return [implode(' AND ', $where), $params, $join];
    }

    /**
     * Neutralise les jokers de LIKE dans une saisie utilisateur.
     */
    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Le tag principal doit figurer parmi les tags de l'operation.
     *
     * Aucune contrainte SQL ne l'exprime, c'est donc verifie ici. A defaut de
     * choix valide, le premier tag fait l'affaire : une operation taggee doit
     * toujours peser sur un pole, sinon elle disparaitrait du camembert.
     *
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
