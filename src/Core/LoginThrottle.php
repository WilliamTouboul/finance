<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Limitation des tentatives de connexion.
 *
 * Un mot de passe solide ne suffit pas : sans limitation, un attaquant peut
 * essayer des milliers de combinaisons par minute. On compte les echecs sur une
 * fenetre glissante et on verrouille temporairement au-dela d'un seuil.
 *
 * Deux compteurs independants :
 *  - par adresse IP, contre une attaque menee depuis une machine ;
 *  - par email, contre une attaque distribuee sur plusieurs IP.
 *
 * Le seuil par email est volontairement plus haut que par IP : un seuil trop
 * bas permettrait a n'importe qui de verrouiller un compte a volonte, ce qui
 * transformerait la protection en deni de service.
 *
 * Note sur les requetes : la duree de fenetre est injectee dans le SQL par
 * interpolation et non par parametre lie. Ce n'est pas une faille, c'est une
 * contrainte de MySQL, qui n'accepte pas de parametre prepare dans une clause
 * INTERVAL ni dans un LIMIT. La valeur interpolee est une constante de classe
 * typee int, jamais une donnee venant du client -- qui, elle, passe toujours
 * par un parametre lie.
 */
final class LoginThrottle
{
    private const WINDOW_MINUTES    = 15;
    private const MAX_FAILURES_IP   = 10;
    private const MAX_FAILURES_MAIL = 20;

    public static function isLocked(string $ip, string $email): bool
    {
        return self::failuresForIp($ip) >= self::MAX_FAILURES_IP
            || self::failuresForEmail($email) >= self::MAX_FAILURES_MAIL;
    }

    /**
     * Temps restant avant deverrouillage, en secondes.
     *
     * Le verrou se leve quand la plus ancienne tentative encore comptabilisee
     * sort de la fenetre glissante. Sert a afficher un message utile plutot
     * qu'un refus opaque.
     */
    public static function secondsUntilUnlock(string $ip, string $email): int
    {
        $window = self::WINDOW_MINUTES;

        $oldest = Database::value(
            "SELECT MIN(attempted_at) FROM login_attempts
              WHERE succeeded = 0
                AND attempted_at > (NOW() - INTERVAL {$window} MINUTE)
                AND (ip_address = ? OR email = ?)",
            [self::packIp($ip), $email]
        );

        if (!is_string($oldest)) {
            return 0;
        }

        $unlockAt = strtotime($oldest) + $window * 60;

        return max(0, $unlockAt - time());
    }

    public static function record(string $ip, string $email, bool $succeeded): void
    {
        Database::run(
            'INSERT INTO login_attempts (ip_address, email, succeeded) VALUES (?, ?, ?)',
            [self::packIp($ip), mb_substr($email, 0, 190), $succeeded ? 1 : 0]
        );
    }

    /**
     * Efface les echecs apres une connexion reussie : l'utilisateur legitime
     * ne doit pas rester penalise par ses propres fautes de frappe.
     */
    public static function clearFailures(string $ip, string $email): void
    {
        Database::run(
            'DELETE FROM login_attempts
              WHERE succeeded = 0 AND (ip_address = ? OR email = ?)',
            [self::packIp($ip), $email]
        );
    }

    /**
     * Supprime l'historique devenu inutile. Appele de temps en temps depuis la
     * page de connexion, ce qui evite d'installer une tache planifiee pour si peu.
     */
    public static function purgeOld(): void
    {
        Database::run('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
    }

    private static function failuresForIp(string $ip): int
    {
        $window = self::WINDOW_MINUTES;

        return (int) Database::value(
            "SELECT COUNT(*) FROM login_attempts
              WHERE succeeded = 0
                AND ip_address = ?
                AND attempted_at > (NOW() - INTERVAL {$window} MINUTE)",
            [self::packIp($ip)]
        );
    }

    private static function failuresForEmail(string $email): int
    {
        $window = self::WINDOW_MINUTES;

        return (int) Database::value(
            "SELECT COUNT(*) FROM login_attempts
              WHERE succeeded = 0
                AND email = ?
                AND attempted_at > (NOW() - INTERVAL {$window} MINUTE)",
            [$email]
        );
    }

    /**
     * Compacte l'adresse IP en binaire : 4 octets en IPv4, 16 en IPv6.
     * Plus compact qu'une chaine, et surtout sans ambiguite de representation
     * (une meme adresse IPv6 peut s'ecrire de plusieurs facons).
     */
    private static function packIp(string $ip): string
    {
        $packed = @inet_pton($ip);

        // Une IP illisible ne doit pas faire echouer la connexion :
        // on retombe sur une valeur neutre qui reste comptabilisable.
        return $packed === false ? (string) inet_pton('0.0.0.0') : $packed;
    }
}
