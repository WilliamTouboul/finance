<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Acces a la base via PDO.
 *
 * La connexion est paresseuse : elle n'est ouverte qu'au premier appel reel.
 * Toutes les requetes passent par des requetes preparees.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host    = (string) Config::get('db.host', '127.0.0.1');
        $port    = (int) Config::get('db.port', 3306);
        $name    = (string) Config::get('db.name', '');
        $charset = (string) Config::get('db.charset', 'utf8mb4');

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

        try {
            self::$pdo = new PDO(
                $dsn,
                (string) Config::get('db.user', ''),
                (string) Config::get('db.password', ''),
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    // Requetes reellement preparees cote serveur, pas d'emulation.
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_STRINGIFY_FETCHES  => false,
                ]
            );
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Connexion a la base impossible : ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }

        return self::$pdo;
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $statement = self::pdo()->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function value(string $sql, array $params = []): mixed
    {
        $value = self::run($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    public static function lastInsertId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }

    /**
     * Execute un ensemble d'ecritures dans une transaction.
     * Toute exception declenche un rollback et est propagee.
     *
     * Reservee aux ecritures de DONNEES (INSERT / UPDATE / DELETE) : creer une
     * operation et ses liaisons de tags en un seul bloc atomique, par exemple.
     *
     * A ne PAS utiliser autour de DDL (CREATE / ALTER / DROP) : MySQL declenche
     * un commit implicite sur ces instructions, ce qui ferme la transaction a
     * l'insu de l'appelant et fait echouer le commit() final.
     */
    public static function transaction(callable $work): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();

        try {
            $result = $work($pdo);
            $pdo->commit();

            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
