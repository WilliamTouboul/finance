<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\Period;
use App\Model\Budget;

/**
 * Seul point d'acces a la table budgets.
 */
final class BudgetRepository
{
    /**
     * Les budgets accompagnes de leur consommation sur la periode.
     *
     * La consommation porte sur primary_tag_id et non sur la table de liaison :
     * une operation ne doit consommer qu'un seul budget, celui de son pole
     * principal. Compter chacun de ses tags ferait consommer plusieurs
     * plafonds a une depense unique.
     *
     * LEFT JOIN : un budget sans aucune depense doit apparaitre a zero, pas
     * disparaitre de la liste.
     *
     * @return array<int, Budget>
     */
    public function withSpending(int $userId, Period $period): array
    {
        // Les marqueurs du LEFT JOIN precedent ceux du WHERE : les parametres
        // positionnels se lient dans l'ordre d'apparition dans la requete.
        $rows = Database::all(
            'SELECT b.id, b.amount_cents,
                    t.id AS tag_id, t.name AS tag_name, t.color AS tag_color,
                    COALESCE(SUM(CASE WHEN o.amount_cents < 0 THEN -o.amount_cents ELSE 0 END), 0) AS spent
               FROM budgets b
               JOIN tags t ON t.id = b.tag_id
               LEFT JOIN operations o
                      ON o.primary_tag_id = b.tag_id
                     AND o.user_id = b.user_id
                     AND o.occurred_on BETWEEN ? AND ?
              WHERE b.user_id = ?
              GROUP BY b.id, b.amount_cents, t.id, t.name, t.color
              ORDER BY t.name',
            [$period->startSql(), $period->endSql(), $userId]
        );

        $months = $period->monthsCovered();

        return array_map(
            static fn (array $row): Budget => Budget::fromRow($row, $months),
            $rows
        );
    }

    /**
     * Budgets definis, sans calcul de consommation. Sert aux ecrans de reglage.
     *
     * @return array<int, array{tag_id: int, amount_cents: int}>
     */
    public function amountsByTag(int $userId): array
    {
        $rows = Database::all(
            'SELECT tag_id, amount_cents FROM budgets WHERE user_id = ?',
            [$userId]
        );

        $byTag = [];
        foreach ($rows as $row) {
            $byTag[(int) $row['tag_id']] = (int) $row['amount_cents'];
        }

        return $byTag;
    }

    /**
     * Cree ou met a jour le budget d'un pole.
     *
     * ON DUPLICATE KEY UPDATE s'appuie sur la contrainte d'unicite
     * (user_id, tag_id) : une seule requete la ou un SELECT puis un INSERT ou
     * UPDATE en demanderait deux, avec le risque qu'une ecriture concurrente
     * se glisse entre les deux.
     */
    public function save(int $userId, int $tagId, int $amountCents): void
    {
        Database::run(
            'INSERT INTO budgets (user_id, tag_id, amount_cents)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE amount_cents = VALUES(amount_cents)',
            [$userId, $tagId, abs($amountCents)]
        );
    }

    public function delete(int $userId, int $tagId): void
    {
        Database::run('DELETE FROM budgets WHERE user_id = ? AND tag_id = ?', [$userId, $tagId]);
    }

    /**
     * Budgets depasses sur la periode, pour le rappel du tableau de bord.
     *
     * @return array<int, Budget>
     */
    public function exceeded(int $userId, Period $period): array
    {
        if ($period->monthsCovered() === 0) {
            return [];
        }

        return array_values(array_filter(
            $this->withSpending($userId, $period),
            static fn (Budget $b): bool => $b->isExceeded()
        ));
    }
}
