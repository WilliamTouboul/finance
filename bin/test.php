<?php

declare(strict_types=1);

/**
 * Lance les suites de tests.
 *
 * Usage : php bin/test.php
 *
 * Decouvre tous les fichiers tests/*Test.php, instancie la classe qu'ils
 * declarent et execute chaque methode commencant par "test".
 *
 * Aucune des suites ne touche a la base : elles portent sur des calculs purs,
 * ce qui les rend executables n'importe ou, y compris sans MySQL demarre.
 */

use App\Core\ConsolePrompt as Console;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'execute uniquement en ligne de commande.\n");
}

// Autoload, journalisation et masquage des arguments dans les traces.
$root = require dirname(__DIR__) . '/config/bootstrap.php';

require $root . '/tests/TestCase.php';

$files = glob($root . '/tests/*Test.php') ?: [];
sort($files);

if ($files === []) {
    exit("Aucune suite de tests trouvee dans tests/\n");
}

$totalAssertions = 0;
$totalFailures   = 0;

foreach ($files as $file) {
    require_once $file;

    $class = 'Tests\\' . basename($file, '.php');

    if (!class_exists($class)) {
        Console::error("La suite {$file} ne declare pas la classe {$class}.");
        $totalFailures++;

        continue;
    }

    /** @var Tests\TestCase $suite */
    $suite = new $class();

    foreach (get_class_methods($suite) as $method) {
        if (str_starts_with($method, 'test')) {
            $suite->{$method}();
        }
    }

    $totalAssertions += $suite->assertions;
    $totalFailures   += count($suite->failures);

    printf(
        "  %-22s %3d assertions   %s\n",
        $suite->name(),
        $suite->assertions,
        $suite->failures === [] ? 'ok' : count($suite->failures) . ' ECHEC(S)'
    );

    foreach ($suite->failures as $failure) {
        echo '      - ' . $failure . PHP_EOL;
    }
}

echo PHP_EOL;

if ($totalFailures === 0) {
    Console::success("{$totalAssertions} assertions, aucun echec.");
    exit(0);
}

Console::error("{$totalAssertions} assertions, {$totalFailures} echec(s).");
exit(1);
