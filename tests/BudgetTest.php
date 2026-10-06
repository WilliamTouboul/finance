<?php

declare(strict_types=1);

namespace Tests;

use App\Model\Budget;
use App\Model\Tag;

/**
 * Consommation et seuils d'alerte des budgets.
 */
final class BudgetTest extends TestCase
{
    public function name(): string
    {
        return 'Budget';
    }

    private function budget(int $ceiling, int $spent, int $months = 1): Budget
    {
        return new Budget(1, new Tag(1, 'loisirs', '#8B5CF6'), $ceiling, $spent, $months);
    }

    public function testConsumption(): void
    {
        $budget = $this->budget(30000, 15000);

        $this->assertApprox(0.5, $budget->share(), 'moitie consommee');
        $this->assertSame(15000, $budget->remainingCents(), 'reste la moitie');
        $this->assertSame('ok', $budget->level(), 'etat normal');
    }

    public function testWarningThreshold(): void
    {
        $this->assertSame('ok', $this->budget(30000, 23000)->level(), 'sous le seuil');
        $this->assertSame('warning', $this->budget(30000, 24000)->level(), 'exactement au seuil de 80 pour cent');
        $this->assertSame('warning', $this->budget(30000, 29000)->level(), 'proche du plafond');
    }

    public function testExceeded(): void
    {
        $budget = $this->budget(30000, 36000);

        $this->assertSame('exceeded', $budget->level(), 'depassement');
        $this->assertTrue($budget->isExceeded(), 'depassement detecte');
        $this->assertSame(-6000, $budget->remainingCents(), 'reste negatif');
        $this->assertApprox(1.0, $budget->barShare(), 'la barre est plafonnee a 100 pour cent');
        $this->assertApprox(1.2, $budget->share(), 'la part reelle depasse 100 pour cent');
    }

    public function testExactCeilingIsNotExceeded(): void
    {
        // Depenser exactement son budget n'est pas un depassement.
        $budget = $this->budget(30000, 30000);

        $this->assertFalse($budget->isExceeded(), 'au plafond exact');
        $this->assertSame(0, $budget->remainingCents(), 'il ne reste rien');
    }

    public function testScalesWithPeriod(): void
    {
        // Un budget se definit au mois : sur une annee il se compare a douze fois plus.
        $budget = $this->budget(30000, 300000, 12);

        $this->assertSame(360000, $budget->ceilingCents(), 'plafond annuel');
        $this->assertFalse($budget->isExceeded(), 'pas de depassement sur l annee');
        $this->assertSame(60000, $budget->remainingCents(), 'reste sur l annee');
    }

    public function testZeroCeilingDoesNotDivideByZero(): void
    {
        $this->assertApprox(0.0, $this->budget(0, 5000)->share(), 'plafond nul');
    }
}
