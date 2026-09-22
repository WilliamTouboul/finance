<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Configuration applicative, chargee une fois depuis config/config.php.
 *
 * Les valeurs se lisent en notation pointee : Config::get('db.host').
 */
final class Config
{
    /** @var array<string, mixed> */
    private static array $values = [];

    private static bool $loaded = false;

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            throw new RuntimeException(
                "Configuration absente : {$file}\n"
                . "Copiez config/config.example.php en config/config.php puis renseignez vos identifiants."
            );
        }

        $values = require $file;

        if (!is_array($values)) {
            throw new RuntimeException("La configuration {$file} doit retourner un tableau.");
        }

        self::$values = $values;
        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::$loaded) {
            throw new RuntimeException('Config::load() doit etre appele avant Config::get().');
        }

        $value = self::$values;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public static function isDev(): bool
    {
        return self::get('env', 'prod') === 'dev';
    }
}
