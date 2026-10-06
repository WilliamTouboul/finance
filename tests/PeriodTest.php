<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Granularity;
use App\Core\Period;
use DateTimeImmutable;

/**
 * Bornes et navigation temporelle.
 *
 * Les calculs de dates concentrent les cas limites : fins de mois, annees
 * bissextiles, passages d'annee.
 */
final class PeriodTest extends TestCase
{
    public function name(): string
    {
        return 'Period';
    }

    private function bounds(Period $p): string
    {
        return $p->startSql() . ' -> ' . $p->endSql();
    }

    private function currentMonthParam(): string
    {
        return (new DateTimeImmutable())->format('Y-m');
    }

    public function testBounds(): void
    {
        $this->assertSame('2026-09-01 -> 2026-09-30', $this->bounds(Period::month(2026, 9)), 'septembre');
        $this->assertSame('2026-02-01 -> 2026-02-28', $this->bounds(Period::month(2026, 2)), 'fevrier ordinaire');
        $this->assertSame('2024-02-01 -> 2024-02-29', $this->bounds(Period::month(2024, 2)), 'fevrier bissextile');
        $this->assertSame('2026-01-01 -> 2026-12-31', $this->bounds(Period::year(2026)), 'annee entiere');
        $this->assertSame('2026-09-15 -> 2026-09-15', $this->bounds(Period::fromParam('2026-09-15')), 'journee');
    }

    public function testParsesUrlParameter(): void
    {
        $this->assertSame(Granularity::Year, Period::fromParam('2026')->granularity, 'annee');
        $this->assertSame(Granularity::Month, Period::fromParam('2026-09')->granularity, 'mois');
        $this->assertSame(Granularity::Day, Period::fromParam('2026-09-15')->granularity, 'jour');
    }

    public function testFallsBackOnInvalidInput(): void
    {
        // Une URL trafiquee ne doit pas produire d'erreur, juste la vue par defaut.
        $fallback = $this->currentMonthParam();

        $this->assertSame($fallback, Period::fromParam('2026-06-31')->toParam(), '31 juin inexistant');
        $this->assertSame($fallback, Period::fromParam('2026-13')->toParam(), 'treizieme mois');
        $this->assertSame($fallback, Period::fromParam('abc')->toParam(), 'texte');
        $this->assertSame($fallback, Period::fromParam("2026' OR 1=1--")->toParam(), 'tentative d injection');
        $this->assertSame('2100', Period::fromParam('9999')->toParam(), 'annee bornee');
    }

    public function testNavigation(): void
    {
        $this->assertSame('2025-12', Period::month(2026, 1)->previous()->toParam(), 'janvier vers decembre');
        $this->assertSame('2027-01', Period::month(2026, 12)->next()->toParam(), 'decembre vers janvier');
        $this->assertSame('2026-02', Period::month(2026, 3)->previous()->toParam(), 'mars vers fevrier');
        $this->assertSame('2027', Period::year(2026)->next()->toParam(), 'annee suivante');
        $this->assertSame('2026-03-01', Period::fromParam('2026-02-28')->next()->toParam(), 'fin de fevrier');
        $this->assertSame('2024-02-29', Period::fromParam('2024-02-28')->next()->toParam(), 'veille du 29 fevrier');
    }

    public function testMonthEndDoesNotDrift(): void
    {
        // Reculer puis avancer doit ramener au point de depart, meme quand le
        // mois traverse est plus court.
        $march = Period::month(2026, 3);

        $this->assertSame(
            $march->toParam(),
            $march->previous()->next()->toParam(),
            'aller-retour sur fevrier'
        );
    }

    public function testGranularitySwitch(): void
    {
        $day = Period::fromParam('2026-09-15');

        $this->assertSame('2026-09', $day->withGranularity(Granularity::Month)->toParam(), 'jour vers mois');
        $this->assertSame('2026', $day->withGranularity(Granularity::Year)->toParam(), 'jour vers annee');
        $this->assertSame('2026-01', Period::year(2026)->withGranularity(Granularity::Month)->toParam(), 'annee vers mois');
    }

    public function testMonthsCovered(): void
    {
        // Sert a mettre un budget mensuel a l'echelle de la periode consultee.
        $this->assertSame(1, Period::month(2026, 9)->monthsCovered(), 'un mois');
        $this->assertSame(12, Period::year(2026)->monthsCovered(), 'douze mois dans une annee');
        $this->assertSame(0, Period::fromParam('2026-09-15')->monthsCovered(), 'aucun sur une journee');
    }

    public function testLabels(): void
    {
        $this->assertSame('septembre 2026', Period::month(2026, 9)->label(), 'libelle mensuel');
        $this->assertSame('2026', Period::year(2026)->label(), 'libelle annuel');
        $this->assertSame("1 ao\u{00FB}t 2026", Period::fromParam('2026-08-01')->label(), 'libelle journalier');
        $this->assertSame(
            'lundi 5 octobre 2026',
            Period::formatDayHeading(new DateTimeImmutable('2026-10-05')),
            'entete de regroupement'
        );
    }
}
