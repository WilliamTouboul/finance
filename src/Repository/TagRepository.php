<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Model\Tag;

/**
 * Seul point d'acces a la table tags.
 *
 * Toutes les methodes prennent un identifiant d'utilisateur et l'imposent dans
 * la clause WHERE, y compris celles qui ciblent une ligne precise par son id.
 * Sans cela, changer le numero dans une URL suffirait a lire ou supprimer le
 * tag de quelqu'un d'autre -- la faille dite "reference directe non securisee".
 */
final class TagRepository
{
    /**
     * @return array<int, Tag>
     */
    public function allForUser(int $userId): array
    {
        $rows = Database::all(
            'SELECT id, name, color FROM tags WHERE user_id = ? ORDER BY name',
            [$userId]
        );

        return array_map(Tag::fromRow(...), $rows);
    }

    /**
     * Les tags avec le nombre d'operations qui les portent.
     *
     * LEFT JOIN et non JOIN : un tag jamais utilise doit apparaitre malgre tout,
     * avec un compteur a zero.
     *
     * @return array<int, Tag>
     */
    public function allWithUsage(int $userId): array
    {
        $rows = Database::all(
            'SELECT t.id, t.name, t.color, COUNT(ot.operation_id) AS usage_count
               FROM tags t
               LEFT JOIN operation_tag ot ON ot.tag_id = t.id
              WHERE t.user_id = ?
              GROUP BY t.id, t.name, t.color
              ORDER BY t.name',
            [$userId]
        );

        return array_map(Tag::fromRow(...), $rows);
    }

    public function find(int $id, int $userId): ?Tag
    {
        $row = Database::one(
            'SELECT id, name, color FROM tags WHERE id = ? AND user_id = ? LIMIT 1',
            [$id, $userId]
        );

        return $row === null ? null : Tag::fromRow($row);
    }

    public function create(int $userId, string $name, string $color): int
    {
        Database::run(
            'INSERT INTO tags (user_id, name, color) VALUES (?, ?, ?)',
            [$userId, $name, $color]
        );

        return Database::lastInsertId();
    }

    public function update(int $id, int $userId, string $name, string $color): void
    {
        Database::run(
            'UPDATE tags SET name = ?, color = ? WHERE id = ? AND user_id = ?',
            [$name, $color, $id, $userId]
        );
    }

    /**
     * La contrainte ON DELETE CASCADE de operation_tag retire au passage les
     * liaisons vers les operations. Les operations elles-memes sont conservees :
     * supprimer un pole de depense ne doit pas effacer l'historique.
     */
    public function delete(int $id, int $userId): void
    {
        Database::run('DELETE FROM tags WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    /**
     * Un tag de meme nom existe-t-il deja chez cet utilisateur ?
     *
     * $excludeId sert lors d'une modification : le tag en cours d'edition ne
     * doit pas entrer en conflit avec lui-meme.
     */
    public function nameExists(int $userId, string $name, ?int $excludeId = null): bool
    {
        $sql    = 'SELECT 1 FROM tags WHERE user_id = ? AND name = ?';
        $params = [$userId, $name];

        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }

        return Database::value($sql . ' LIMIT 1', $params) !== null;
    }

    /**
     * Ne conserve, parmi les identifiants fournis, que ceux appartenant
     * reellement a l'utilisateur.
     *
     * Le formulaire d'operation renvoie des identifiants de tags choisis par le
     * client : ils sont donc suspects par nature. Les faire passer par cette
     * methode garantit qu'une requete forgee ne rattachera pas une operation au
     * tag d'un autre compte.
     *
     * @param array<int, int> $ids
     * @return array<int, int>
     */
    public function keepOwned(int $userId, array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            return [];
        }

        // Un marqueur par identifiant : la liste reste entierement parametree,
        // aucune valeur n'est concatenee dans la requete.
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));

        $rows = Database::all(
            "SELECT id FROM tags WHERE user_id = ? AND id IN ({$placeholders})",
            array_merge([$userId], $ids)
        );

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }
}
