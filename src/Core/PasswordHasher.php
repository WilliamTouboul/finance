<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Hachage des mots de passe, avec choix automatique du meilleur algorithme
 * disponible sur l'environnement d'execution.
 *
 * Pourquoi ne pas figer l'algorithme : Argon2id (laureat de la Password Hashing
 * Competition, resistant aux attaques GPU et ASIC grace a son cout memoire)
 * est preferable a bcrypt, mais il n'est pas garanti sur un hebergement
 * mutualise. Plutot que de risquer une application qui refuse de demarrer en
 * production, on choisit a l'execution et on laisse PHP migrer les hachages
 * existants via needsRehash().
 *
 * Aucun mot de passe n'est jamais stocke, journalise ou compare en clair.
 */
final class PasswordHasher
{
    /**
     * Cout bcrypt utilise en repli. 12 correspond a la recommandation OWASP :
     * environ 250 ms par verification sur un serveur courant, negligeable pour
     * une connexion legitime, prohibitif pour une attaque par dictionnaire.
     */
    private const BCRYPT_COST = 12;

    /**
     * Hachages factices utilises par verifyDummy(), precalcules et figes ici.
     *
     * Ce ne sont pas des secrets : ce sont les empreintes d'une chaine sans
     * usage, qui ne correspond a aucun compte. Les publier dans le depot n'a
     * aucune consequence.
     *
     * Pourquoi les figer plutot que les generer a la volee : generer un hachage
     * Argon2id coute le meme prix que d'en verifier un. Le faire a chaque
     * requete doublait le temps de reponse pour un email inconnu et recreait
     * exactement la fuite d'information que verifyDummy() est cense supprimer.
     *
     * Leurs parametres doivent rester alignes sur options() : c'est ce qui
     * garantit un cout de verification identique a celui d'un compte reel.
     */
    private const DUMMY_ARGON2ID = '$argon2id$v=19$m=65536,t=4,p=1$QTFLVTZQRVBCcC9DVC4yVA$THVbilQDnfY4lBX85GMKIbDjDr/cD9QrEih6LAWUi2U';

    private const DUMMY_BCRYPT = '$2y$12$hbqsHNP.GhA7i.kLnfQQhukYE5vaOu2.f14abEz9LaSxrPcT8/ANW';

    public static function algorithm(): string
    {
        return defined('PASSWORD_ARGON2ID') && in_array('argon2id', password_algos(), true)
            ? PASSWORD_ARGON2ID
            : PASSWORD_BCRYPT;
    }

    public static function algorithmName(): string
    {
        return self::algorithm() === PASSWORD_BCRYPT ? 'bcrypt' : 'argon2id';
    }

    public static function hash(string $plain): string
    {
        return password_hash($plain, self::algorithm(), self::options());
    }

    public static function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    /**
     * Le hachage doit-il etre refait ? Vrai si l'algorithme ou ses parametres
     * ont change depuis la creation du compte (changement d'hebergement,
     * montee de version PHP, augmentation du cout).
     */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, self::algorithm(), self::options());
    }

    /**
     * Consomme le meme temps de calcul qu'une verification reelle.
     *
     * Sans cela, une reponse instantanee pour un email inconnu et une reponse
     * lente pour un email connu permettraient d'enumerer les comptes existants
     * en chronometrant simplement les requetes.
     */
    public static function verifyDummy(string $plain): void
    {
        $dummy = self::algorithm() === PASSWORD_BCRYPT
            ? self::DUMMY_BCRYPT
            : self::DUMMY_ARGON2ID;

        // Le resultat est volontairement ignore : seul le temps passe compte.
        password_verify($plain, $dummy);
    }

    /**
     * @return array<string, int>
     */
    private static function options(): array
    {
        if (self::algorithm() === PASSWORD_BCRYPT) {
            return ['cost' => self::BCRYPT_COST];
        }

        // Valeurs par defaut de PHP pour Argon2id : 64 Mio de memoire,
        // 4 iterations, 1 thread. Superieures aux minimas OWASP.
        return [
            'memory_cost' => PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
            'time_cost'   => PASSWORD_ARGON2_DEFAULT_TIME_COST,
            'threads'     => PASSWORD_ARGON2_DEFAULT_THREADS,
        ];
    }
}
