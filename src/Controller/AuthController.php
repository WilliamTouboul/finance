<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Csrf;
use App\Core\LoginThrottle;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Connexion et deconnexion.
 */
final class AuthController extends BaseController
{
    /**
     * Message unique pour tous les echecs d'identification.
     *
     * Distinguer "email inconnu" de "mot de passe incorrect" confirmerait a un
     * attaquant l'existence d'un compte. Le confort perdu est minime, le
     * renseignement offert ne l'est pas.
     */
    private const GENERIC_ERROR = 'Identifiants incorrects.';

    public function showLogin(Request $request): Response
    {
        if ($this->auth->check()) {
            return $this->redirect('/');
        }

        // Entretien opportuniste de la table des tentatives : quelques
        // suppressions de temps en temps evitent une tache planifiee.
        if (random_int(1, 20) === 1) {
            LoginThrottle::purgeOld();
        }

        return $this->view('auth/login', [
            'pageTitle' => 'Connexion',
            'email'     => $request->query('email', ''),
            'error'     => null,
        ], 200, 'layout/auth');
    }

    public function login(Request $request): Response
    {
        if ($this->auth->check()) {
            return $this->redirect('/');
        }

        $email    = mb_strtolower((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');
        $ip       = self::clientIp();

        if (!Csrf::isValid($request->input(Csrf::FIELD_NAME))) {
            // Jeton absent ou perimee : le plus souvent un formulaire laisse
            // ouvert trop longtemps, parfois une tentative de CSRF.
            return $this->loginError($email, 'Votre formulaire a expiré. Merci de réessayer.');
        }

        if (LoginThrottle::isLocked($ip, $email)) {
            $minutes = (int) ceil(LoginThrottle::secondsUntilUnlock($ip, $email) / 60);

            return $this->loginError(
                $email,
                "Trop de tentatives. Réessayez dans {$minutes} minute(s).",
                429
            );
        }

        if ($email === '' || $password === '') {
            return $this->loginError($email, self::GENERIC_ERROR);
        }

        $user = $this->auth->attempt($email, $password);

        LoginThrottle::record($ip, $email, $user !== null);

        if ($user === null) {
            return $this->loginError($email, self::GENERIC_ERROR);
        }

        LoginThrottle::clearFailures($ip, $email);
        $this->auth->login($user);

        Session::flash('success', 'Bienvenue, ' . $user->displayName . '.');

        return $this->redirect('/');
    }

    public function logout(Request $request): Response
    {
        // La deconnexion passe par POST et non par GET : un simple lien ou une
        // image pointant vers /deconnexion suffirait sinon a deconnecter
        // l'utilisateur depuis n'importe quelle page tierce.
        if (!Csrf::isValid($request->input(Csrf::FIELD_NAME))) {
            return $this->redirect('/');
        }

        $this->auth->logout();

        Session::start();
        Session::flash('info', 'Vous êtes déconnecté.');

        return $this->redirect('/connexion');
    }

    private function loginError(string $email, string $message, int $status = 401): Response
    {
        return $this->view('auth/login', [
            'pageTitle' => 'Connexion',
            'email'     => $email,
            'error'     => $message,
        ], $status, 'layout/auth');
    }

    /**
     * Adresse IP du client.
     *
     * On lit REMOTE_ADDR et rien d'autre. Les en-tetes X-Forwarded-For sont
     * fournis par le client et donc falsifiables : s'y fier permettrait de
     * contourner la limitation de tentatives en changeant d'en-tete a chaque
     * essai. Derriere un reverse proxy de confiance, il faudra lire cet
     * en-tete, mais seulement apres avoir valide l'IP du proxy.
     */
    private static function clientIp(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}
