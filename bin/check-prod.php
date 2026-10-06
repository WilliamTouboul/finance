<?php

declare(strict_types=1);

/**
 * Verification avant mise en ligne.
 *
 * Usage : php bin/check-prod.php
 *
 * Passe en revue ce qui distingue une installation de developpement d'une
 * installation exposee sur Internet. Une liste papier se deroule de memoire et
 * saute toujours une ligne ; ce script, non.
 *
 * Code de sortie : 0 si tout est vert, 1 si au moins un point bloquant reste.
 */

use App\Core\Config;
use App\Core\Database;
use App\Core\PasswordHasher;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'execute uniquement en ligne de commande.\n");
}

// Autoload, journalisation et masquage des arguments dans les traces.
$root = require dirname(__DIR__) . '/config/bootstrap.php';

$problems = 0;
$warnings = 0;

function ok(string $label, string $detail = ''): void
{
    printf("  [ok]   %-46s %s\n", $label, $detail);
}

function bad(string $label, string $detail): void
{
    global $problems;
    $problems++;
    printf("  [!!]   %-46s %s\n", $label, $detail);
}

function warn(string $label, string $detail): void
{
    global $warnings;
    $warnings++;
    printf("  [~]    %-46s %s\n", $label, $detail);
}

echo "Verification avant mise en ligne\n";
echo "================================\n\n";

// --------------------------------------------------------------- Configuration

try {
    Config::load($root . '/config/config.php');
} catch (Throwable $e) {
    echo "  [!!] Configuration illisible : " . $e->getMessage() . PHP_EOL;
    exit(1);
}

echo "Configuration\n";

Config::isDev()
    ? bad("env n'est pas en production", "les traces d'erreur seront affichees aux visiteurs")
    : ok('env', 'prod');

(bool) Config::get('session.secure', false)
    ? ok('session.secure', 'le cookie de session exige HTTPS')
    : bad('session.secure est a false', 'le cookie de session circulera en clair');

$dbPassword = (string) Config::get('db.password', '');

if ($dbPassword === '') {
    bad('mot de passe de base vide', 'inacceptable sur un serveur partage');
} elseif (mb_strlen($dbPassword) < 12) {
    warn('mot de passe de base court', mb_strlen($dbPassword) . ' caracteres');
} else {
    ok('mot de passe de base', mb_strlen($dbPassword) . ' caracteres');
}

Config::get('db.user') === 'root'
    ? warn("compte de base 'root'", 'preferez un compte dedie a cette seule base')
    : ok('compte de base', (string) Config::get('db.user'));

// -------------------------------------------------------------- Environnement

echo "\nEnvironnement\n";

version_compare(PHP_VERSION, '8.2', '>=')
    ? ok('version de PHP', PHP_VERSION)
    : bad('version de PHP trop ancienne', PHP_VERSION . ', 8.2 minimum attendu');

foreach (['pdo_mysql', 'mbstring', 'openssl'] as $extension) {
    extension_loaded($extension)
        ? ok("extension {$extension}", 'chargee')
        : bad("extension {$extension} absente", "l'application ne fonctionnera pas");
}

ok('hachage des mots de passe', PasswordHasher::algorithmName());

$logDir = $root . '/var/log';

is_dir($logDir) && is_writable($logDir)
    ? ok('var/log accessible en ecriture', '')
    : bad('var/log non accessible en ecriture', 'les erreurs ne seront pas journalisees');

ini_get('zend.exception_ignore_args') === '1'
    ? ok('arguments masques dans les traces', '')
    : warn('arguments visibles dans les traces', 'config/bootstrap.php devrait les masquer');

// --------------------------------------------------------------------- Donnees

echo "\nBase de donnees\n";

try {
    Database::pdo();
    ok('connexion', (string) Config::get('db.host') . ':' . (string) Config::get('db.port'));
} catch (Throwable $e) {
    bad('connexion impossible', $e->getMessage());
    echo "\nVerification interrompue : la base est injoignable.\n";
    exit(1);
}

$expected = count(glob($root . '/database/migrations/*.sql') ?: []);
$applied  = (int) Database::value('SELECT COUNT(*) FROM migrations');

$applied === $expected
    ? ok('migrations appliquees', "{$applied} / {$expected}")
    : bad('migrations en retard', "{$applied} / {$expected}, lancez php bin/migrate.php");

$realAccounts = (int) Database::value('SELECT COUNT(*) FROM users WHERE is_demo = 0');

$realAccounts === 0
    ? bad('aucun compte', 'lancez php bin/create-user.php')
    : ok('comptes reels', (string) $realAccounts);

// Un compte de test oublie en production est une porte ouverte : son mot de
// passe a souvent ete choisi pour etre tape vite, et il traine dans un depot.
$suspects = Database::all(
    "SELECT email FROM users
      WHERE is_demo = 0
        AND (email LIKE '%test%' OR email LIKE '%demo%' OR email LIKE '%@local%' OR email LIKE '%example%')"
);

if ($suspects === []) {
    ok('aucun compte de test residuel', '');
} else {
    foreach ($suspects as $row) {
        bad('compte de test en base', (string) $row['email']);
    }
}

$staleDemos = (int) Database::value(
    'SELECT COUNT(*) FROM users WHERE is_demo = 1 AND expires_at < NOW()'
);

$staleDemos === 0
    ? ok('aucune demonstration expiree', '')
    : warn('demonstrations expirees en attente', "{$staleDemos}, purgees a la prochaine ouverture");

// --------------------------------------------------------------- Exposition

echo "\nFichiers\n";

is_file($root . '/public/index.php')
    ? ok('front controller', 'public/index.php')
    : bad('front controller introuvable', 'public/index.php');

is_file($root . '/.htaccess')
    ? ok('garde-fou a la racine', 'bloque tout si le document root est mal pointe')
    : warn('pas de garde-fou a la racine', 'un document root mal pointe exposerait config/');

$versioned = trim((string) @shell_exec('git ls-files config/config.php 2>&1'));

$versioned === ''
    ? ok('config/config.php non versionne', '')
    : bad('config/config.php est versionne', 'vos identifiants sont dans le depot');

// ------------------------------------------------------------------ Resultat

echo "\n";

if ($problems > 0) {
    echo "{$problems} point(s) bloquant(s)";
    echo $warnings > 0 ? ", {$warnings} avertissement(s).\n" : ".\n";
    echo "Corrigez-les avant d'ouvrir l'acces.\n";
    exit(1);
}

echo $warnings > 0
    ? "Aucun point bloquant, {$warnings} avertissement(s) a examiner.\n"
    : "Tout est vert : l'installation peut etre ouverte.\n";

exit(0);
