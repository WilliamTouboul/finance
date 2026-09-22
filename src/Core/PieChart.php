<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Camembert dessine en SVG, calcule cote serveur.
 *
 * Aucune bibliotheque graphique : le projet n'a pas de dependance et n'en
 * gagne pas une pour tracer des arcs de cercle. Le graphique s'affiche sans
 * JavaScript, reste net a l'impression et a n'importe quel zoom.
 *
 * Le calcul des parts est separe du rendu : la repartition se teste sans
 * avoir a comparer des chaines SVG, et la legende se sert des memes parts que
 * le dessin -- elles ne peuvent donc pas diverger.
 */
final class PieChart
{
    /**
     * @param int   $size      cote du carre SVG, en pixels.
     * @param float $threshold part en dessous de laquelle une tranche rejoint
     *                         "Autres". Sous ce seuil les tranches deviennent
     *                         des traits illisibles et leur libelle se chevauche.
     */
    public function __construct(
        private readonly int $size = 260,
        private readonly float $threshold = 0.03,
    ) {
    }

    /**
     * Repartit les valeurs en tranches exploitables.
     *
     * @param array<int, array{label: string, value: int, color: string, link?: string|null}> $data
     * @return array<int, array{label: string, value: int, color: string, share: float, start: float, end: float, link: string|null}>
     */
    public function computeSlices(array $data): array
    {
        $data = array_values(array_filter($data, static fn (array $d): bool => $d['value'] > 0));

        $total = array_sum(array_column($data, 'value'));

        if ($total <= 0) {
            return [];
        }

        usort($data, static fn (array $a, array $b): int => $b['value'] <=> $a['value']);

        $kept  = [];
        $small = [];

        foreach ($data as $item) {
            if ($item['value'] / $total < $this->threshold) {
                $small[] = $item;
            } else {
                $kept[] = $item;
            }
        }

        // Regrouper une seule petite tranche sous "Autres" n'apporte rien et
        // rend le graphique moins lisible qu'en la nommant.
        if (count($small) === 1) {
            $kept  = [...$kept, ...$small];
            $small = [];
        }

        if ($small !== []) {
            $kept[] = [
                'label' => 'Autres',
                'value' => array_sum(array_column($small, 'value')),
                'color' => '#9CA3AF',
            ];
        }

        $slices = [];
        $angle  = 0.0;

        foreach ($kept as $item) {
            $share = $item['value'] / $total;
            $sweep = $share * 360.0;

            $slices[] = [
                'label' => $item['label'],
                'value' => $item['value'],
                'color' => $item['color'],
                'share' => $share,
                'start' => $angle,
                'end'   => $angle + $sweep,
                // Destination au clic, quand la tranche en designe une. Les
                // tranches regroupees sous "Autres" n'en ont pas : elles ne
                // correspondent a aucun pole unique.
                'link'  => $item['link'] ?? null,
            ];

            $angle += $sweep;
        }

        return $slices;
    }

    /**
     * @param array<int, array{label: string, value: int, color: string, share: float, start: float, end: float, link: string|null}> $slices
     */
    public function render(array $slices): string
    {
        if ($slices === []) {
            return '';
        }

        $center = $this->size / 2;
        $radius = $center - 1;

        $svg = '<svg class="pie" viewBox="0 0 ' . $this->size . ' ' . $this->size . '"'
            . ' width="' . $this->size . '" height="' . $this->size . '"'
            . ' role="img" xmlns="http://www.w3.org/2000/svg">';

        // Une part unique couvre 360 degres : le point de depart de l'arc et son
        // point d'arrivee se confondent alors, et la commande A ne dessine rien
        // du tout. Ce cas se traite avec un cercle plein.
        if (count($slices) === 1) {
            $svg .= '<circle cx="' . $center . '" cy="' . $center . '" r="' . $radius . '"'
                . ' fill="' . View::e($slices[0]['color']) . '"/>';
        } else {
            foreach ($slices as $slice) {
                $svg .= $this->wrapWithLink($slice, $this->slicePath($center, $radius, $slice));
            }
        }

        return $svg . '</svg>';
    }

    /**
     * @param array{label: string, value: int, color: string, share: float, start: float, end: float, link: string|null} $slice
     */
    private function slicePath(float $center, float $radius, array $slice): string
    {
        // Le <title> donne une infobulle native au survol, sans une ligne de
        // JavaScript, et c'est aussi ce que lira un lecteur d'ecran.
        return '<path class="pie__slice" d="'
            . $this->arcPath($center, $radius, $slice['start'], $slice['end']) . '"'
            . ' fill="' . View::e($slice['color']) . '"'
            . ' stroke="#ffffff" stroke-width="1.5">'
            . '<title>' . View::e($slice['label']) . ' — '
            . View::e(Money::format($slice['value'])) . ' ('
            . View::e($this->formatShare($slice['share'])) . ')</title>'
            . '</path>';
    }

    /**
     * @param array{link: string|null} $slice
     */
    private function wrapWithLink(array $slice, string $path): string
    {
        if ($slice['link'] === null) {
            return $path;
        }

        return '<a href="' . View::e($slice['link']) . '">' . $path . '</a>';
    }

    public function formatShare(float $share): string
    {
        return number_format($share * 100, 1, ',', ' ') . ' %';
    }

    /**
     * Chemin SVG d'une part : du centre vers le debut de l'arc, l'arc, retour au centre.
     */
    private function arcPath(float $center, float $radius, float $startAngle, float $endAngle): string
    {
        [$x1, $y1] = $this->pointAt($center, $radius, $startAngle);
        [$x2, $y2] = $this->pointAt($center, $radius, $endAngle);

        // Au-dela d'un demi-tour, SVG a besoin qu'on lui precise de prendre le
        // grand arc : sans ce drapeau il tracerait le petit, et la part
        // apparaitrait comme son complement.
        $largeArc = ($endAngle - $startAngle) > 180.0 ? 1 : 0;

        return sprintf(
            'M %s %s L %s %s A %s %s 0 %d 1 %s %s Z',
            $this->round($center),
            $this->round($center),
            $this->round($x1),
            $this->round($y1),
            $this->round($radius),
            $this->round($radius),
            $largeArc,
            $this->round($x2),
            $this->round($y2),
        );
    }

    /**
     * Point du cercle a un angle donne.
     *
     * Les angles sont comptes depuis midi et dans le sens horaire, comme on lit
     * un camembert. En trigonometrie l'origine est a 3 heures et le sens est
     * inverse, d'ou la rotation de 90 degres.
     *
     * @return array{0: float, 1: float}
     */
    private function pointAt(float $center, float $radius, float $angleDegrees): array
    {
        $radians = deg2rad($angleDegrees - 90.0);

        return [
            $center + $radius * cos($radians),
            $center + $radius * sin($radians),
        ];
    }

    /**
     * Trois decimales suffisent au pixel pres et gardent le SVG lisible.
     */
    private function round(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
