<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Frequency;
use App\Core\Schedule;
use DateTimeImmutable;

/**
 * Echeances des operations recurrentes.
 */
final class ScheduleTest extends TestCase
{
    public function name(): string
    {
        return 'Schedule';
    }

    private function at(string $start, Frequency $f, int $index): string
    {
        return Schedule::occurrenceAt(new DateTimeImmutable($start), $f, $index)->format('Y-m-d');
    }

    /**
     * @return array<int, string>
     */
    private function until(string $start, Frequency $f, string $until, ?string $ends = null): array
    {
        return array_map(
            static fn (DateTimeImmutable $d): string => $d->format('Y-m-d'),
            Schedule::occurrencesUntil(
                new DateTimeImmutable($start),
                $f,
                new DateTimeImmutable($until),
                $ends !== null ? new DateTimeImmutable($ends) : null
            )
        );
    }

    public function testMonthly(): void
    {
        $this->assertSame('2026-01-05', $this->at('2026-01-05', Frequency::Monthly, 0), 'premiere echeance');
        $this->assertSame('2026-02-05', $this->at('2026-01-05', Frequency::Monthly, 1), 'mois suivant');
        $this->assertSame('2027-01-05', $this->at('2026-01-05', Frequency::Monthly, 12), 'un an plus tard');
    }

    public function testMonthEndDoesNotDrift(): void
    {
        // Une echeance au 31 tombe au dernier jour des mois plus courts, puis
        // revient au 31 des que possible. Un simple "+1 month" en PHP donnerait
        // le 3 mars depuis le 31 janvier, et la date deriverait ensuite.
        $this->assertSame('2026-02-28', $this->at('2026-01-31', Frequency::Monthly, 1), 'fevrier');
        $this->assertSame('2026-03-31', $this->at('2026-01-31', Frequency::Monthly, 2), 'retour au 31 en mars');
        $this->assertSame('2026-04-30', $this->at('2026-01-31', Frequency::Monthly, 3), 'avril');
        $this->assertSame('2026-05-31', $this->at('2026-01-31', Frequency::Monthly, 4), 'retour au 31 en mai');
    }

    public function testLeapYears(): void
    {
        $this->assertSame('2025-02-28', $this->at('2024-02-29', Frequency::Yearly, 1), '29 fevrier vers annee ordinaire');
        $this->assertSame('2028-02-29', $this->at('2024-02-29', Frequency::Yearly, 4), 'retour sur une bissextile');
        $this->assertSame('2024-02-29', $this->at('2024-01-30', Frequency::Monthly, 1), '30 janvier vers fevrier bissextile');
    }

    public function testOtherFrequencies(): void
    {
        $this->assertSame('2026-04-15', $this->at('2026-01-15', Frequency::Quarterly, 1), 'trimestre');
        $this->assertSame('2027-03-20', $this->at('2026-03-20', Frequency::Yearly, 1), 'annee');
        $this->assertSame('2026-10-13', $this->at('2026-10-06', Frequency::Weekly, 1), 'semaine');

        $this->assertSame(
            (new DateTimeImmutable('2026-10-06'))->format('N'),
            (new DateTimeImmutable($this->at('2026-10-06', Frequency::Weekly, 7)))->format('N'),
            'le jour de la semaine ne change pas'
        );
    }

    public function testSeriesUntilDate(): void
    {
        $this->assertSame(
            ['2026-01-05', '2026-02-05', '2026-03-05', '2026-04-05'],
            $this->until('2026-01-05', Frequency::Monthly, '2026-04-30'),
            'quatre mois'
        );

        $this->assertSame(
            ['2026-01-05', '2026-02-05', '2026-03-05'],
            $this->until('2026-01-05', Frequency::Monthly, '2026-03-05'),
            'la borne est incluse'
        );

        $this->assertSame(
            ['2026-01-05', '2026-02-05'],
            $this->until('2026-01-05', Frequency::Monthly, '2026-03-04'),
            'la veille de la borne exclut l echeance'
        );

        $this->assertSame([], $this->until('2026-06-01', Frequency::Monthly, '2026-03-01'), 'depart posterieur a la borne');
    }

    public function testEndDate(): void
    {
        $this->assertSame(
            ['2026-01-05', '2026-02-05', '2026-03-05'],
            $this->until('2026-01-05', Frequency::Monthly, '2026-12-31', '2026-03-31'),
            'la fin de recurrence borne la serie'
        );

        $this->assertSame(
            3,
            count($this->until('2026-01-10', Frequency::Monthly, '2030-01-01', '2026-03-10')),
            'credit a trois mensualites'
        );
    }

    public function testNextAfter(): void
    {
        $next = Schedule::nextAfter(
            new DateTimeImmutable('2026-01-05'),
            Frequency::Monthly,
            new DateTimeImmutable('2026-03-07')
        );
        $this->assertSame('2026-04-05', $next?->format('Y-m-d'), 'prochaine apres le 7 mars');

        $next = Schedule::nextAfter(
            new DateTimeImmutable('2026-01-05'),
            Frequency::Monthly,
            new DateTimeImmutable('2026-05-01'),
            new DateTimeImmutable('2026-04-30')
        );
        $this->assertSame(null, $next, 'recurrence terminee');
    }

    public function testSeriesIsBounded(): void
    {
        // Une recurrence hebdomadaire demarree il y a trente ans ne doit pas
        // proposer des milliers d'echeances d'un coup.
        $this->assertTrue(
            count($this->until('1990-01-01', Frequency::Weekly, '2026-10-06')) <= Schedule::MAX_OCCURRENCES,
            'la serie reste bornee'
        );
    }
}
