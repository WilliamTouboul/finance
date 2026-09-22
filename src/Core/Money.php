<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Conversion et affichage des montants.
 *
 * Regle du projet : un montant est TOUJOURS un entier de centimes en memoire
 * et en base. Les flottants ne sont jamais utilises, meme temporairement --
 * 0.1 + 0.2 !== 0.3 en binaire, et une erreur d'arrondi sur des comptes
 * personnels est inacceptable.
 */
final class Money
{
    /**
     * Convertit une saisie utilisateur en centimes.
     *
     * Accepte "12,50", "12.50", "-12.5", "1 234,56", "+8".
     * Retourne null si la saisie n'est pas un montant valide.
     */
    public static function parse(string $input): ?int
    {
        // Espaces (y compris insecables) utilises comme separateurs de milliers.
        $normalized = str_replace(["\u{00A0}", "\u{202F}", ' '], '', trim($input));
        $normalized = str_replace(',', '.', $normalized);

        if ($normalized === '' || preg_match('/^[+-]?\d+(\.\d{1,2})?$/', $normalized) !== 1) {
            return null;
        }

        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');

        [$units, $decimals] = array_pad(explode('.', $normalized, 2), 2, '0');

        // str_pad garantit deux decimales : "5" -> "50" centimes, pas "5".
        $cents = (int) $units * 100 + (int) str_pad($decimals, 2, '0', STR_PAD_RIGHT);

        return $negative ? -$cents : $cents;
    }

    /**
     * Formate des centimes pour l'affichage : -4250 -> "-42,50 €".
     */
    public static function format(int $cents, bool $withSymbol = true): string
    {
        $formatted = number_format(abs($cents) / 100, 2, ',', ' ');

        if ($cents < 0) {
            $formatted = '-' . $formatted;
        }

        return $withSymbol ? $formatted . ' €' : $formatted;
    }

    /**
     * Comme format(), mais avec un "+" explicite sur les montants positifs.
     * Utilise dans les listes d'operations, ou distinguer recette et depense
     * d'un coup d'oeil compte plus que la concision.
     */
    public static function formatSigned(int $cents, bool $withSymbol = true): string
    {
        $prefix = $cents > 0 ? '+' : '';

        return $prefix . self::format($cents, $withSymbol);
    }

    /**
     * Valeur decimale sous forme de chaine, pour pre-remplir un champ de formulaire.
     */
    public static function toInput(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
