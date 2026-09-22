<?php

declare(strict_types=1);

/**
 * Applique les migrations SQL non encore executees.
 *
 * Usage : php bin/migrate.php
 *
 * Chaque fichier de database/migrations/ est joue une seule fois ; la table
 * `migrations` garde la trace de ce qui a deja ete applique.
 */

use App\Core\Autoloader;
use App\Core\Config;
use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'execute uniquement en ligne de commande.\n");
}

$root = dirname(__DIR__);

require $root . '/src/Core/Autoloader.php';
Autoloader::register($root . '/src');

try {
    Config::load($root . '/config/config.php');
} catch (Throwable $e) {
    exit('[!] ' . $e->getMessage() . PHP_EOL);
}

ensureDatabaseExists($root);

try {
    $pdo = Database::pdo();
} catch (Throwable $e) {
    exit('[!] ' . $e->getMessage() . PHP_EOL);
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS migrations (
        id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
        filename    VARCHAR(255) NOT NULL,
        applied_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_migrations_filename (filename)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$applied = $pdo->query('SELECT filename FROM migrations')->fetchAll(PDO::FETCH_COLUMN);

$files = glob($root . '/database/migrations/*.sql') ?: [];
sort($files);

$pending = array_filter(
    $files,
    static fn (string $file): bool => !in_array(basename($file), $applied, true)
);

if ($pending === []) {
    exit('Rien a faire : ' . count($files) . ' migration(s) deja appliquee(s).' . PHP_EOL);
}

/**
 * Volontairement sans transaction.
 *
 * MySQL declenche un COMMIT implicite sur chaque instruction DDL (CREATE,
 * ALTER, DROP) : la transaction ouverte est validee avant meme que le DDL
 * s'execute, et le commit() final leve alors "There is no active transaction".
 * Contrairement a PostgreSQL, MySQL ne sait pas annuler un changement de schema.
 *
 * On assume donc l'absence d'atomicite, mais on la rend visible : en cas
 * d'echec, le script indique exactement quelle instruction a casse et quelles
 * instructions sont deja passees, pour permettre un nettoyage manuel.
 */
foreach ($pending as $file) {
    $name = basename($file);
    echo "-> {$name} ... ";

    $statements = splitStatements((string) file_get_contents($file));
    $done = 0;

    foreach ($statements as $index => $statement) {
        try {
            $pdo->exec($statement);
            $done++;
        } catch (Throwable $e) {
            echo 'ECHEC' . PHP_EOL;
            echo '[!] Instruction ' . ($index + 1) . '/' . count($statements)
                . ' : ' . $e->getMessage() . PHP_EOL;
            echo '    ' . preg_replace('/\s+/', ' ', mb_substr($statement, 0, 120)) . PHP_EOL;

            if ($done > 0) {
                echo PHP_EOL
                    . "    Attention : {$done} instruction(s) ont deja ete appliquees et MySQL" . PHP_EOL
                    . '    ne peut pas les annuler (commit implicite sur le DDL).' . PHP_EOL
                    . '    Corrigez la migration puis nettoyez la base a la main avant de relancer.' . PHP_EOL;
            }

            exit(1);
        }
    }

    // Enregistre apres coup : une migration n'est marquee appliquee que si
    // toutes ses instructions sont reellement passees.
    $pdo->prepare('INSERT INTO migrations (filename) VALUES (?)')->execute([$name]);

    echo 'ok (' . count($statements) . ' instruction(s))' . PHP_EOL;
}

echo count($pending) . ' migration(s) appliquee(s).' . PHP_EOL;

/**
 * Decoupe un fichier SQL en instructions.
 * Les migrations du projet restent volontairement simples (DDL et INSERT) :
 * pas de procedure stockee, donc pas besoin de gerer les delimiteurs.
 *
 * @return array<int, string>
 */
function splitStatements(string $sql): array
{
    $withoutComments = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;

    $statements = array_map('trim', explode(';', $withoutComments));

    return array_values(array_filter($statements, static fn (string $s): bool => $s !== ''));
}

/**
 * Cree la base si elle n'existe pas encore, pour eviter un aller-retour
 * manuel par phpMyAdmin a la premiere installation.
 */
function ensureDatabaseExists(string $root): void
{
    $name = (string) Config::get('db.name', '');

    // Le nom d'une base ne peut pas etre passe en parametre prepare :
    // on le valide strictement avant de l'interpoler.
    if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
        exit("[!] Nom de base invalide dans config/config.php : '{$name}'" . PHP_EOL);
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;charset=%s',
        (string) Config::get('db.host', '127.0.0.1'),
        (int) Config::get('db.port', 3306),
        (string) Config::get('db.charset', 'utf8mb4')
    );

    try {
        $server = new PDO(
            $dsn,
            (string) Config::get('db.user', ''),
            (string) Config::get('db.password', ''),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (PDOException $e) {
        exit(
            '[!] Serveur MySQL injoignable : ' . $e->getMessage() . PHP_EOL
            . '    Verifiez que MySQL est demarre dans le dashboard DevServer.' . PHP_EOL
        );
    }

    $exists = $server
        ->query("SHOW DATABASES LIKE " . $server->quote($name))
        ->fetchColumn();

    if ($exists === false) {
        $server->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "Base '{$name}' creee." . PHP_EOL;
    }
}
