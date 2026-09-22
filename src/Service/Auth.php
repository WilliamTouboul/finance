<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Csrf;
use App\Core\PasswordHasher;
use App\Core\Session;
use App\Core\UnauthorizedException;
use App\Model\User;
use App\Repository\UserRepository;

/**
 * Etat d'authentification de la requete courante.
 *
 * Classe instanciable et non statique, contrairement aux facades Session ou
 * Database : Auth porte une logique metier et une dependance (le repository),
 * ce qui la rend testable en lui injectant un double. Les facades, elles, ne
 * font qu'envelopper une ressource globale du langage.
 *
 * La session ne contient que l'identifiant de l'utilisateur, jamais l'objet
 * complet : si le compte est modifie en base, la page suivante voit les
 * donnees a jour, et aucune donnee sensible ne traine dans le fichier de session.
 */
final class Auth
{
    private const KEY_USER_ID     = '_auth_user_id';
    private const KEY_FINGERPRINT = '_auth_fingerprint';

    private ?User $current = null;

    private bool $resolved = false;

    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
    ) {
    }

    /**
     * Verifie un couple email / mot de passe.
     *
     * Retourne l'utilisateur en cas de succes, null sinon. Le contexte
     * (verrouillage, journalisation, message affiche) est du ressort de
     * l'appelant : cette methode ne fait que valider les identifiants.
     */
    public function attempt(string $email, string $password): ?User
    {
        $user = $this->users->findByEmail($email);

        if ($user === null) {
            // Aucun compte : on consomme quand meme le temps d'un hachage.
            // Une reponse immediate ici, contre une reponse lente pour un email
            // existant, revelerait quels comptes existent.
            PasswordHasher::verifyDummy($password);

            return null;
        }

        if (!PasswordHasher::verify($password, $user->passwordHash)) {
            return null;
        }

        // L'occasion de remettre le hachage au gout du jour : changement
        // d'hebergement, montee de version de PHP, cout augmente. Le mot de
        // passe en clair n'est disponible qu'ici, au moment de la connexion.
        if (PasswordHasher::needsRehash($user->passwordHash)) {
            $this->users->updatePasswordHash($user->id, PasswordHasher::hash($password));
        }

        return $user;
    }

    /**
     * Ouvre la session applicative pour cet utilisateur.
     */
    public function login(User $user): void
    {
        // Nouvel identifiant de session : sans cela, un identifiant obtenu
        // avant la connexion resterait valide apres, ce qui est exactement
        // le scenario d'une attaque par fixation de session.
        Session::regenerate();
        Csrf::rotate();

        Session::set(self::KEY_USER_ID, $user->id);
        Session::set(self::KEY_FINGERPRINT, self::fingerprint());

        $this->users->touchLastLogin($user->id);

        $this->current  = $user;
        $this->resolved = true;
    }

    public function logout(): void
    {
        Session::destroy();

        $this->current  = null;
        $this->resolved = true;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function user(): ?User
    {
        if ($this->resolved) {
            return $this->current;
        }

        $this->resolved = true;

        $id = Session::get(self::KEY_USER_ID);

        if (!is_int($id)) {
            return $this->current = null;
        }

        // Le contexte du navigateur a change en cours de session : on coupe
        // par prudence. Defense secondaire -- un attaquant qui vole le cookie
        // peut aussi imiter l'en-tete -- mais le cout est nul et cela arrete
        // les reutilisations grossieres.
        if (Session::get(self::KEY_FINGERPRINT) !== self::fingerprint()) {
            $this->logout();

            return null;
        }

        $user = $this->users->findById($id);

        if ($user === null) {
            // Le compte a disparu depuis l'ouverture de la session.
            $this->logout();

            return null;
        }

        return $this->current = $user;
    }

    /**
     * @throws UnauthorizedException si personne n'est connecte.
     */
    public function requireUser(): User
    {
        $user = $this->user();

        if ($user === null) {
            throw new UnauthorizedException('Authentification requise.');
        }

        return $user;
    }

    /**
     * Empreinte du contexte client, liee a la session.
     */
    private static function fingerprint(): string
    {
        return hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    }
}
