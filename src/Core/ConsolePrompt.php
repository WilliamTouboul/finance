<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Saisie interactive pour les scripts en ligne de commande.
 */
final class ConsolePrompt
{
    public static function ask(string $label, bool $required = true): string
    {
        while (true) {
            echo $label . ' : ';
            $value = trim((string) fgets(STDIN));

            if ($value !== '' || !$required) {
                return $value;
            }

            self::error('Cette valeur est obligatoire.');
        }
    }

    /**
     * Saisie sans echo a l'ecran.
     *
     * Le masquage repose sur stty, absent des consoles Windows natives. Quand
     * il n'est pas disponible on le dit franchement plutot que de laisser
     * croire que la saisie est masquee.
     */
    public static function askHidden(string $label): string
    {
        if (!self::canHideInput()) {
            self::warn('La saisie ne peut pas etre masquee dans ce terminal : le mot de passe restera visible a l ecran.');

            return self::ask($label);
        }

        echo $label . ' : ';

        shell_exec('stty -echo');
        $value = trim((string) fgets(STDIN));
        shell_exec('stty echo');

        echo PHP_EOL;

        return $value;
    }

    public static function line(string $message = ''): void
    {
        echo $message . PHP_EOL;
    }

    public static function success(string $message): void
    {
        echo '[ok] ' . $message . PHP_EOL;
    }

    public static function warn(string $message): void
    {
        echo '[!] ' . $message . PHP_EOL;
    }

    public static function error(string $message): void
    {
        fwrite(STDERR, '[x] ' . $message . PHP_EOL);
    }

    /**
     * stty est-il utilisable ici ?
     */
    private static function canHideInput(): bool
    {
        if (!function_exists('shell_exec')) {
            return false;
        }

        $probe = shell_exec('stty -g 2>&1');

        // Sur un terminal compatible, stty -g renvoie la configuration
        // courante. Ailleurs il renvoie null ou un message d'erreur.
        return is_string($probe) && $probe !== '' && !str_contains(strtolower($probe), 'not found');
    }
}
