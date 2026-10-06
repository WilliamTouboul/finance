<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Courbe d'evolution dessinee en SVG, calculee cote serveur.
 *
 * Meme parti pris que PieChart : aucune bibliotheque, rendu sans JavaScript,
 * net a l'impression. Le calcul de la geometrie est separe du dessin pour
 * pouvoir se tester sans comparer des chaines SVG.
 *
 * La courbe montre un solde cumule, pas des flux. Une pente qui descend
 * regulierement, c'est un decouvert qui approche -- c'est cela qu'on doit
 * saisir d'un coup d'oeil.
 */
final class LineChart
{
    public function __construct(
        private readonly int $width = 760,
        private readonly int $height = 200,
        /** Marge verticale, pour que la courbe ne colle pas aux bords. */
        private readonly int $padding = 12,
    ) {
    }

    /**
     * Convertit des valeurs en coordonnees.
     *
     * @param array<int, int> $values
     * @return array{points: array<int, array{x: float, y: float, value: int}>, min: int, max: int, zeroY: float|null}
     */
    public function computeGeometry(array $values): array
    {
        $values = array_values($values);
        $count  = count($values);

        if ($count === 0) {
            return ['points' => [], 'min' => 0, 'max' => 0, 'zeroY' => null];
        }

        $min = min($values);
        $max = max($values);

        // Le zero est toujours dans l'echelle quand les valeurs sont du meme
        // signe : sans cela, un solde oscillant entre 2 000 et 2 100 euros
        // produirait une courbe dramatique pour une variation de 5 pour cent.
        $min = min($min, 0);
        $max = max($max, 0);

        $span = $max - $min;

        // Toutes les valeurs egales : une echelle nulle diviserait par zero.
        // On ouvre une plage arbitraire pour que la courbe sorte a plat.
        if ($span === 0) {
            $span = 1;
        }

        $usableHeight = $this->height - 2 * $this->padding;

        $toY = fn (int $value): float =>
            $this->padding + $usableHeight * (1 - ($value - $min) / $span);

        $points = [];

        foreach ($values as $index => $value) {
            $points[] = [
                // Un point unique se place au milieu plutot qu'au bord gauche.
                'x'     => $count === 1
                    ? $this->width / 2
                    : $index * ($this->width / ($count - 1)),
                'y'     => $toY($value),
                'value' => $value,
            ];
        }

        return [
            'points' => $points,
            'min'    => $min,
            'max'    => $max,
            'zeroY'  => $toY(0),
        ];
    }

    /**
     * @param array<int, int>    $values  soldes successifs, en centimes.
     * @param array<int, string> $labels  libelles pour les infobulles, meme ordre.
     */
    public function render(array $values, array $labels = []): string
    {
        $geometry = $this->computeGeometry($values);
        $points   = $geometry['points'];

        if ($points === []) {
            return '';
        }

        $svg = '<svg class="line-chart" viewBox="0 0 ' . $this->width . ' ' . $this->height . '"'
            . ' preserveAspectRatio="none" role="img" xmlns="http://www.w3.org/2000/svg">';

        // Ligne du zero : la seule reference qui compte sur un solde.
        if ($geometry['min'] < 0) {
            $svg .= '<line class="line-chart__zero" x1="0" y1="' . $this->round($geometry['zeroY'])
                . '" x2="' . $this->width . '" y2="' . $this->round($geometry['zeroY']) . '"/>';
        }

        $polyline = implode(' ', array_map(
            fn (array $p): string => $this->round($p['x']) . ',' . $this->round($p['y']),
            $points
        ));

        // Aire sous la courbe, refermee sur la ligne du zero plutot que sur le
        // bas du cadre : un solde negatif doit remplir vers le haut.
        $baseY  = $this->round($geometry['zeroY']);
        $first  = $points[0];
        $last   = $points[count($points) - 1];

        $svg .= '<path class="line-chart__area" d="M ' . $this->round($first['x']) . ' ' . $baseY
            . ' L ' . $polyline
            . ' L ' . $this->round($last['x']) . ' ' . $baseY . ' Z"/>';

        $svg .= '<polyline class="line-chart__line" points="' . $polyline . '"/>';

        // Un seul point ne ferait pas de ligne visible : on le materialise.
        if (count($points) === 1) {
            $svg .= '<circle class="line-chart__dot" cx="' . $this->round($first['x'])
                . '" cy="' . $this->round($first['y']) . '" r="3"/>';
        }

        $svg .= $this->renderTooltips($points, $labels);

        return $svg . '</svg>';
    }

    /**
     * Zones de survol invisibles portant un <title>.
     *
     * Donne une infobulle native sur chaque point sans une ligne de
     * JavaScript. Les rectangles sont transparents et couvrent toute la
     * hauteur : viser un point a quelques pixels pres serait inconfortable.
     *
     * @param array<int, array{x: float, y: float, value: int}> $points
     * @param array<int, string>                                $labels
     */
    private function renderTooltips(array $points, array $labels): string
    {
        $count = count($points);

        if ($count < 2) {
            return '';
        }

        $slotWidth = $this->width / $count;
        $svg       = '';

        foreach ($points as $index => $point) {
            $label = $labels[$index] ?? null;

            if ($label === null) {
                continue;
            }

            $svg .= '<rect class="line-chart__hit" x="' . $this->round($index * $slotWidth)
                . '" y="0" width="' . $this->round($slotWidth) . '" height="' . $this->height . '">'
                . '<title>' . View::e($label) . '</title></rect>';
        }

        return $svg;
    }

    private function round(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
