<?php

declare(strict_types=1);

namespace Tests;

use App\Core\LineChart;
use App\Core\PieChart;

/**
 * Geometrie des graphiques dessines en SVG.
 */
final class ChartTest extends TestCase
{
    public function name(): string
    {
        return 'PieChart + LineChart';
    }

    public function testPieDistribution(): void
    {
        $pie = new PieChart();

        $slices = $pie->computeSlices([
            ['label' => 'loisirs', 'value' => 5000, 'color' => '#111111'],
            ['label' => 'sport',   'value' => 3000, 'color' => '#222222'],
            ['label' => 'courses', 'value' => 2000, 'color' => '#333333'],
        ]);

        $this->assertSame(3, count($slices), 'trois parts');
        $this->assertSame(['loisirs', 'sport', 'courses'], array_column($slices, 'label'), 'tri decroissant');
        $this->assertApprox(0.5, $slices[0]['share'], 'part de la plus grosse tranche');
        $this->assertApprox(0.0, $slices[0]['start'], 'depart a midi');
        $this->assertApprox(360.0, $slices[2]['end'], 'le tour est complet');
        $this->assertApprox(1.0, array_sum(array_column($slices, 'share')), 'les parts totalisent 100 pour cent');
    }

    public function testPieAnglesAreContiguous(): void
    {
        $pie    = new PieChart();
        $slices = $pie->computeSlices([
            ['label' => 'a', 'value' => 700, 'color' => '#111111'],
            ['label' => 'b', 'value' => 200, 'color' => '#222222'],
            ['label' => 'c', 'value' => 100, 'color' => '#333333'],
        ]);

        for ($i = 1; $i < count($slices); $i++) {
            $this->assertApprox(
                $slices[$i - 1]['end'],
                $slices[$i]['start'],
                "aucun trou avant la part {$i}",
                0.001
            );
        }
    }

    public function testPieGroupsSmallSlices(): void
    {
        $pie = new PieChart();

        $slices = $pie->computeSlices([
            ['label' => 'gros',    'value' => 10000, 'color' => '#111111'],
            ['label' => 'moyen',   'value' => 5000,  'color' => '#222222'],
            ['label' => 'miette1', 'value' => 100,   'color' => '#333333'],
            ['label' => 'miette2', 'value' => 80,    'color' => '#444444'],
            ['label' => 'miette3', 'value' => 60,    'color' => '#555555'],
        ]);

        $this->assertSame(['gros', 'moyen', 'Autres'], array_column($slices, 'label'), 'les miettes sont regroupees');
        $this->assertSame(240, $slices[2]['value'], 'total des miettes');

        // Regrouper une seule petite tranche serait moins lisible que la nommer.
        $slices = $pie->computeSlices([
            ['label' => 'gros',   'value' => 10000, 'color' => '#111111'],
            ['label' => 'miette', 'value' => 100,   'color' => '#222222'],
        ]);
        $this->assertSame(['gros', 'miette'], array_column($slices, 'label'), 'pas de regroupement inutile');
    }

    public function testPieFullCircle(): void
    {
        // Un arc de 360 degres a son point de depart confondu avec son point
        // d'arrivee : la commande A de SVG ne dessine alors rien du tout.
        $pie    = new PieChart();
        $slices = $pie->computeSlices([['label' => 'tout', 'value' => 4200, 'color' => '#AA0000']]);
        $svg    = $pie->render($slices);

        $this->assertSame(1, count($slices), 'une seule part');
        $this->assertTrue(str_contains($svg, '<circle'), 'rendue par un cercle');
        $this->assertFalse(str_contains($svg, '<path'), 'aucun arc degenere');
    }

    public function testPieLargeArcFlag(): void
    {
        $pie = new PieChart();
        $svg = $pie->render($pie->computeSlices([
            ['label' => 'enorme', 'value' => 9000, 'color' => '#111111'],
            ['label' => 'petit',  'value' => 1000, 'color' => '#222222'],
        ]));

        preg_match_all('/A [\d.]+ [\d.]+ 0 (\d) 1/', $svg, $matches);

        $this->assertSame('1', $matches[1][0] ?? null, 'grand arc pour la part de 90 pour cent');
        $this->assertSame('0', $matches[1][1] ?? null, 'petit arc pour la part de 10 pour cent');
    }

    public function testPieEmptyCases(): void
    {
        $pie = new PieChart();

        $this->assertSame([], $pie->computeSlices([]), 'aucune donnee');
        $this->assertSame([], $pie->computeSlices([['label' => 'x', 'value' => 0, 'color' => '#000000']]), 'valeur nulle');
        $this->assertSame([], $pie->computeSlices([['label' => 'x', 'value' => -5, 'color' => '#000000']]), 'valeur negative');
        $this->assertSame('', $pie->render([]), 'rendu vide');
    }

    public function testLineGeometry(): void
    {
        $chart = new LineChart(760, 200, 12);
        $g     = $chart->computeGeometry([0, 5000, 10000]);

        $this->assertSame(3, count($g['points']), 'trois points');
        $this->assertApprox(0.0, $g['points'][0]['x'], 'premier point a gauche');
        $this->assertApprox(760.0, $g['points'][2]['x'], 'dernier point a droite');
        $this->assertApprox(12.0, $g['points'][2]['y'], 'maximum en haut');
    }

    public function testLineKeepsZeroInScale(): void
    {
        // Sans cela, un solde oscillant entre 2 000 et 2 100 euros produirait
        // une courbe dramatique pour une variation de cinq pour cent.
        $chart = new LineChart();

        $this->assertSame(0, $chart->computeGeometry([200000, 205000, 210000])['min'], 'le zero reste dans l echelle');
        $this->assertSame(-8000, $chart->computeGeometry([5000, -2000, -8000])['min'], 'un minimum negatif est conserve');
    }

    public function testLineHandlesFlatSeries(): void
    {
        // Toutes les valeurs egales : l'echelle vaudrait zero et diviserait par zero.
        $chart = new LineChart();
        $g     = $chart->computeGeometry([5000, 5000, 5000]);

        $this->assertSame(3, count($g['points']), 'les points existent');
        $this->assertSame(1, count(array_unique(array_column($g['points'], 'y'))), 'courbe plate');
    }

    public function testLineZeroLineOnlyWhenNegative(): void
    {
        $chart = new LineChart();

        $this->assertTrue(str_contains($chart->render([5000, -2000]), 'line-chart__zero'), 'tracee si un solde est negatif');
        $this->assertFalse(str_contains($chart->render([1000, 2000]), 'line-chart__zero'), 'absente si tout est positif');
    }

    public function testLineEmptyAndSinglePoint(): void
    {
        $chart = new LineChart(760, 200, 12);

        $this->assertSame('', $chart->render([]), 'aucune valeur');
        $this->assertApprox(380.0, $chart->computeGeometry([4200])['points'][0]['x'], 'point unique centre');
        $this->assertTrue(str_contains($chart->render([4200]), 'line-chart__dot'), 'point unique materialise');
    }

    public function testLineEscapesLabels(): void
    {
        $chart = new LineChart();

        $this->assertTrue(
            str_contains($chart->render([1, 2], ['<script>', 'x']), '&lt;script&gt;'),
            'les libelles d infobulle sont echappes'
        );
    }
}
