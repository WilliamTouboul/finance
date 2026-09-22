<?php

declare(strict_types=1);

namespace App\Core;

use Random\RandomException;

/**
 * Protection contre le Cross-Site Request Forgery.
 *
 * Principe : un site tiers peut forcer le navigateur de l'utilisateur a
 * envoyer une requete vers cette application (le cookie de session part
 * automatiquement), mais il ne peut pas LIRE le contenu de nos pages. Un jeton
 * imprevisible, place dans chaque formulaire et verifie a la soumission,
 * distingue donc une requete emise depuis l'application d'une requete forgee.
 *
 * Le cookie de session est deja en SameSite=Strict, ce qui bloque le gros des
 * attaques ; ce jeton est la deuxieme ligne de defense, utile face aux
 * navigateurs anciens et aux contournements connus de SameSite.
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public const FIELD_NAME = '_token';

    /**
     * Jeton de la session courante, genere au premier appel.
     *
     * @throws RandomException si la source d'entropie du systeme est indisponible.
     */
    public static function token(): string
    {
        $token = Session::get(self::SESSION_KEY);

        if (!is_string($token) || $token === '') {
            // random_bytes() puise dans le CSPRNG du systeme.
            // rand() et uniqid() seraient predictibles, donc inutilisables ici.
            $token = bin2hex(random_bytes(32));
            Session::set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public static function isValid(?string $submitted): bool
    {
        $expected = Session::get(self::SESSION_KEY);

        if (!is_string($expected) || !is_string($submitted) || $submitted === '') {
            return false;
        }

        // hash_equals compare en temps constant : une comparaison avec ===
        // s'arrete au premier caractere different et permet, en chronometrant
        // les reponses, de reconstituer le jeton caractere par caractere.
        return hash_equals($expected, $submitted);
    }

    /**
     * Renouvelle le jeton. Appele apres une connexion, en meme temps que la
     * regeneration de l'identifiant de session.
     */
    public static function rotate(): void
    {
        Session::remove(self::SESSION_KEY);
        self::token();
    }

    /**
     * Champ cache a inserer dans chaque formulaire POST.
     */
    public static function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD_NAME . '" value="'
            . View::e(self::token()) . '">';
    }
}
