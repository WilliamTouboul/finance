<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Autoloader PSR-4 minimal.
 *
 * Le projet n'a aucune dependance externe : plutot que d'embarquer Composer
 * pour une seule fonctionnalite, on mappe le namespace App\ sur src/.
 */
final class Autoloader
{
    public static function register(string $baseDir, string $prefix = 'App\\'): void
    {
        spl_autoload_register(static function (string $class) use ($baseDir, $prefix): void {
            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            $file = rtrim($baseDir, '\/') . DIRECTORY_SEPARATOR
                . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

            if (is_file($file)) {
                require $file;
            }
        });
    }
}
