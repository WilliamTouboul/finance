<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Model\User;

/**
 * Seul point d'acces a la table users.
 *
 * Centraliser les requetes ici evite que du SQL se disperse dans les
 * controleurs, et garantit qu'une colonne renommee ne se corrige qu'a un
 * seul endroit.
 */
final class UserRepository
{
    private const COLUMNS = 'id, email, display_name, password_hash';

    public function findByEmail(string $email): ?User
    {
        $row = Database::one(
            'SELECT ' . self::COLUMNS . ' FROM users WHERE email = ? LIMIT 1',
            [mb_strtolower(trim($email))]
        );

        return $row === null ? null : User::fromRow($row);
    }

    public function findById(int $id): ?User
    {
        $row = Database::one(
            'SELECT ' . self::COLUMNS . ' FROM users WHERE id = ? LIMIT 1',
            [$id]
        );

        return $row === null ? null : User::fromRow($row);
    }

    /**
     * @return int l'identifiant du compte cree.
     */
    public function create(string $email, string $displayName, string $passwordHash): int
    {
        Database::run(
            'INSERT INTO users (email, display_name, password_hash) VALUES (?, ?, ?)',
            [mb_strtolower(trim($email)), trim($displayName), $passwordHash]
        );

        return Database::lastInsertId();
    }

    public function updatePasswordHash(int $userId, string $passwordHash): void
    {
        Database::run(
            'UPDATE users SET password_hash = ? WHERE id = ?',
            [$passwordHash, $userId]
        );
    }

    public function touchLastLogin(int $userId): void
    {
        Database::run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$userId]);
    }

    public function countAll(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM users');
    }

    public function emailExists(string $email): bool
    {
        return Database::value(
            'SELECT 1 FROM users WHERE email = ? LIMIT 1',
            [mb_strtolower(trim($email))]
        ) !== null;
    }
}
