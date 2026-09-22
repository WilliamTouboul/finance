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
     * Trois strategies, essayees dans l'ordre :
     *   1. stty, sous Unix et macOS ;
     *   2. Read-Host -AsSecureString, sous Windows ;
     *   3. saisie visible, en le disant franchement.
     *
     * La voie stty ne sert jamais sous Windows, meme lance depuis Git Bash :
     * PHP y execute exec() par cmd.exe, qui ne connait pas stty -- et quand
     * bien meme, la commande agirait sur le terminal de cmd.exe et non sur
     * celui qui recoit la frappe.
     *
     * Le dernier recours affiche un avertissement plutot que de laisser croire
     * a un masquage : se tromper dans ce sens ferait taper un mot de passe en
     * clair a quelqu'un qui se croit protege.
     */
    public static function askHidden(string $label): string
    {
        if (self::hasStty()) {
            echo $label . ' : ';

            shell_exec('stty -echo');
            $value = trim((string) fgets(STDIN));
            shell_exec('stty echo');

            echo PHP_EOL;

            return $value;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $value = self::askHiddenWindows($label);

            if ($value !== null) {
                return $value;
            }
        }

        self::warn('La saisie ne peut pas etre masquee dans ce terminal : ce que vous tapez restera visible a l ecran.');

        return self::ask($label);
    }

    /**
     * Masquage via PowerShell sur les consoles Windows natives.
     *
     * Read-Host -AsSecureString masque la frappe ; la valeur est ensuite
     * reconvertie en texte pour nous etre transmise par la sortie standard.
     * Elle ne touche ni le disque ni l'historique des commandes.
     *
     * @return string|null null si PowerShell est indisponible ou echoue.
     */
    private static function askHiddenWindows(string $label): ?string
    {
        if (!function_exists('shell_exec')) {
            return null;
        }

        $script = '$s = Read-Host -AsSecureString; '
            . '[Runtime.InteropServices.Marshal]::PtrToStringAuto('
            . '[Runtime.InteropServices.Marshal]::SecureStringToBSTR($s))';

        echo $label . ' : ';

        $value = shell_exec('powershell -NoProfile -NonInteractive:$false -Command ' . escapeshellarg($script));

        if (!is_string($value)) {
            echo PHP_EOL;

            return null;
        }

        return trim($value);
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
     *
     * La detection porte sur le code de retour et non sur le texte de sortie.
     * Une premiere version cherchait la chaine "not found" : sous un Windows
     * francais, le message est "n'est pas reconnu en tant que commande
     * interne", donc la detection concluait que stty fonctionnait et le
     * masquage n'avait jamais lieu -- en silence, ce qui est le pire cas pour
     * une saisie de mot de passe. Un code de retour ne parle aucune langue.
     */
    private static function hasStty(): bool
    {
        if (!function_exists('exec')) {
            return false;
        }

        $output   = [];
        $exitCode = 1;

        exec('stty -g 2>&1', $output, $exitCode);

        return $exitCode === 0;
    }
}
