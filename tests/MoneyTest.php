<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Money;

/**
 * Conversion et affichage des montants.
 *
 * Le coeur de l'application : une erreur ici fausse tous les totaux sans
 * jamais lever d'exception.
 */
final class MoneyTest extends TestCase
{
    public function name(): string
    {
        return 'Money';
    }

    public function testParsesCommonFormats(): void
    {
        $this->assertSame(1250, Money::parse('12,50'), 'virgule decimale');
        $this->assertSame(1250, Money::parse('12.50'), 'point decimal');
        $this->assertSame(-4200, Money::parse('-42'), 'entier negatif');
        $this->assertSame(800, Money::parse('+8'), 'signe plus explicite');
        $this->assertSame(123456, Money::parse('1 234,56'), 'separateur de milliers');
        $this->assertSame(0, Money::parse('0'), 'zero');
    }

    public function testCompletesMissingDecimal(): void
    {
        // "12.5" vaut douze euros cinquante, pas douze euros cinq centimes.
        $this->assertSame(1250, Money::parse('12.5'), 'une seule decimale');
        $this->assertSame(-5, Money::parse('-0,05'), 'centimes seuls');
    }

    public function testRejectsInvalidInput(): void
    {
        $this->assertSame(null, Money::parse(''), 'chaine vide');
        $this->assertSame(null, Money::parse('abc'), 'texte');
        $this->assertSame(null, Money::parse('1.234'), 'trois decimales');
        $this->assertSame(null, Money::parse('12,,5'), 'ponctuation doublee');
    }

    public function testFormatsForDisplay(): void
    {
        $this->assertSame("-42,50 \u{20AC}", Money::format(-4250), 'montant negatif');
        $this->assertSame("1 234,56 \u{20AC}", Money::format(123456), 'separateur de milliers');
        $this->assertSame("+8,00 \u{20AC}", Money::formatSigned(800), 'signe explicite sur une recette');
        $this->assertSame('-42.50', Money::toInput(-4250), 'valeur pour un champ de formulaire');
    }

    public function testRoundTrip(): void
    {
        // Ce qui sort d'un formulaire doit pouvoir y rentrer a nouveau.
        foreach ([-123456, -5, 0, 1, 99, 100000] as $cents) {
            $this->assertSame(
                $cents,
                Money::parse(Money::toInput($cents)),
                "aller-retour sur {$cents} centimes"
            );
        }
    }
}
