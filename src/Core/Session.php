<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session PHP durcie.
 *
 * Les reglages sont appliques AVANT session_start() : une fois la session
 * demarree, la plupart des directives n'ont plus d'effet.
 */
final class Session
{
    private const KEY_LAST_ACTIVITY = '_last_activity';
    private const KEY_FLASHES       = '_flashes';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Nom personnalise : "PHPSESSID" annonce gratuitement la technologie
        // employee a tout attaquant qui regarde les cookies.
        session_name((string) Config::get('session.name', 'app_session'));

        session_set_cookie_params([
            // lifetime 0 : le cookie expire a la fermeture du navigateur.
            // L'expiration reelle est geree cote serveur par checkIdleTimeout().
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            // Le cookie n'est transmis qu'en HTTPS. A activer dans la config
            // des que le site est servi en TLS.
            'secure'   => (bool) Config::get('session.secure', false),
            // Le cookie est invisible pour JavaScript : un XSS ne peut pas
            // voler l'identifiant de session.
            'httponly' => true,
            // Le cookie n'accompagne aucune requete venant d'un autre site,
            // ce qui neutralise la majorite des attaques CSRF.
            'samesite' => 'Strict',
        ]);

        // Refuse tout identifiant de session non genere par le serveur.
        // Sans cela, un attaquant peut imposer un identifiant connu de lui
        // et reutiliser la session une fois la victime connectee (session fixation).
        ini_set('session.use_strict_mode', '1');
        // L'identifiant de session ne circule que par cookie, jamais dans
        // l'URL, ou il fuirait par l'en-tete Referer et les journaux serveur.
        ini_set('session.use_only_cookies', '1');

        // Pas de reglage de session.sid_length ni de session.sid_bits_per_character :
        // ces directives sont depreciees depuis PHP 8.4. Depuis PHP 7.1 les
        // identifiants sont tires du CSPRNG du systeme et les valeurs par
        // defaut sont sures ; les ajuster n'apportait plus rien.

        session_start();

        self::checkIdleTimeout();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Change l'identifiant de session en conservant les donnees.
     *
     * A appeler a chaque changement de niveau de privilege, donc au minimum
     * juste apres une connexion reussie.
     */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];

        // Le cookie ne disparait pas tout seul : il faut le faire expirer
        // explicitement cote client.
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Strict',
            ]);
        }

        session_destroy();
    }

    /**
     * Message a afficher une seule fois, au chargement suivant.
     * Utilise apres une redirection ("Operation enregistree", "Session expiree").
     */
    public static function flash(string $type, string $message): void
    {
        $_SESSION[self::KEY_FLASHES][] = ['type' => $type, 'message' => $message];
    }

    /**
     * Recupere les messages en attente et les efface dans la foulee.
     *
     * @return array<int, array{type: string, message: string}>
     */
    public static function takeFlashes(): array
    {
        $flashes = $_SESSION[self::KEY_FLASHES] ?? [];
        unset($_SESSION[self::KEY_FLASHES]);

        return is_array($flashes) ? $flashes : [];
    }

    /**
     * Deconnecte apres une periode d'inactivite.
     *
     * Le controle est fait cote serveur : une expiration qui ne reposerait que
     * sur la duree de vie du cookie serait modifiable par le client.
     */
    private static function checkIdleTimeout(): void
    {
        $lifetime = (int) Config::get('session.lifetime', 7200);
        $last     = $_SESSION[self::KEY_LAST_ACTIVITY] ?? null;

        if (is_int($last) && (time() - $last) > $lifetime) {
            // On vide le contenu et on change d'identifiant plutot que de
            // detruire la session : detruire puis relancer dans la meme requete
            // rappellerait start(), donc cette methode, pour rien.
            $_SESSION = [];
            session_regenerate_id(true);

            self::flash('info', "Votre session a expiré après une période d'inactivité.");
        }

        $_SESSION[self::KEY_LAST_ACTIVITY] = time();
    }
}
