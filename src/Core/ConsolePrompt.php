<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Saisie interactive pour les scripts en ligne de commande.
 */
final class ConsolePrompt
{
    /**
     * Longueur au-dela de laquelle une saisie ne peut plus venir d'un clavier.
     *
     * Ce garde-fou existe a cause d'un incident reel : une tentative de
     * masquage deleguee a un sous-processus renvoyait, en cas d'echec, son
     * message d'erreur sur la sortie standard. Ce message a ete pris pour un
     * mot de passe, a passe la verification de longueur minimale et a servi a
     * creer un compte inutilisable. Une valeur destinee a un mot de passe ne
     * doit jamais etre acceptee sans controle de plausibilite.
     */
    private const MAX_INPUT_LENGTH = 256;

    /**
     * L'avertissement sur la frappe visible ne se dit qu'une fois par
     * execution : repete a chaque champ, il devient du bruit qu'on n'ecoute plus.
     */
    private static bool $visibleInputWarned = false;

    public static function ask(string $label, bool $required = true): string
    {
        while (true) {
            echo $label . ' : ';
            $value = self::readLine();

            // Entree standard fermee : insister n'a aucun sens, la question
            // suivante lirait la meme fin de fichier et le script tournerait
            // en boucle sans jamais rien afficher d'utile.
            if ($value === null) {
                self::line();
                self::error('Entree standard fermee : ce script doit etre lance dans un terminal interactif.');
                exit(1);
            }

            if ($value !== '' || !$required) {
                return $value;
            }

            self::error('Cette valeur est obligatoire.');
        }
    }

    /**
     * Saisie d'un secret.
     *
     * Sous Unix et macOS, stty coupe l'echo du terminal et la frappe est
     * reellement invisible.
     *
     * Sous Windows, aucun masquage fiable n'est possible depuis PHP. La
     * delegation a PowerShell (Read-Host -AsSecureString) a ete essayee puis
     * abandonnee : elle se bloque indefiniment des que l'entree standard n'est
     * pas une console, ce qui la rend intestable et dangereuse. On affiche donc
     * la frappe en le disant, puis on efface la ligne aussitot la saisie
     * validee, pour que le secret ne reste pas lisible a l'ecran.
     */
    public static function askHidden(string $label): string
    {
        if (self::hasStty()) {
            echo $label . ' : ';

            shell_exec('stty -echo');
            $value = self::readLine();
            shell_exec('stty echo');

            echo PHP_EOL;

            return self::requireLine($value);
        }

        if (!self::$visibleInputWarned) {
            self::warn('Ce terminal ne permet pas de masquer la frappe : votre saisie sera visible le temps de la taper.');
            self::$visibleInputWarned = true;
        }

        echo $label . ' : ';
        $value = self::readLine();

        self::eraseLastLine();

        return self::requireLine($value);
    }

    /**
     * Interrompt proprement si l'entree standard est fermee.
     */
    private static function requireLine(?string $value): string
    {
        if ($value === null) {
            self::line();
            self::error('Entree standard fermee : ce script doit etre lance dans un terminal interactif.');
            exit(1);
        }

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
     * Lit une ligne sur l'entree standard, avec garde-fous.
     *
     * @return string|null null quand l'entree standard est fermee (fin de
     *                     fichier), a distinguer d'une ligne vide : la premiere
     *                     situation est definitive, la seconde non.
     */
    private static function readLine(): ?string
    {
        $raw = fgets(STDIN);

        if ($raw === false) {
            return null;
        }

        // Une saisie clavier tient sur une ligne. Tout ce qui depasse vient
        // d'ailleurs et n'a rien a faire ici.
        $value = trim($raw);

        if (mb_strlen($value) > self::MAX_INPUT_LENGTH) {
            self::error('Saisie anormalement longue, ignoree.');

            return '';
        }

        return $value;
    }

    /**
     * Remonte d'une ligne et l'efface, pour retirer un secret de l'ecran.
     *
     * L'effacement n'a lieu que si le terminal interprete les sequences ANSI.
     * Sur une console qui ne les comprend pas, les emettre afficherait des
     * caracteres parasites tout en laissant le mot de passe bien lisible :
     * le contraire de l'effet recherche.
     */
    private static function eraseLastLine(): void
    {
        if (!self::supportsAnsi()) {
            return;
        }

        echo "\033[1A\033[2K\r";
    }

    /**
     * Le terminal comprend-il les sequences ANSI ?
     *
     * Sous Windows, sapi_windows_vt100_support() avec son second argument tente
     * d'activer le mode VT100 et indique si la console l'accepte -- ce que les
     * consoles recentes font, mais pas les plus anciennes.
     */
    private static function supportsAnsi(): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return function_exists('sapi_windows_vt100_support')
                && sapi_windows_vt100_support(STDOUT, true);
        }

        return true;
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
     *
     * A noter : sous Windows, cette detection echoue toujours, y compris
     * lancee depuis Git Bash. PHP y execute exec() par cmd.exe, qui ne connait
     * pas stty -- et quand bien meme, la commande agirait sur le terminal de
     * cmd.exe et non sur celui qui recoit la frappe.
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
