<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Un pole de depense libre, cree par l'utilisateur.
 *
 * Les tags ne sont pas une liste figee : ils se creent au fil de l'eau et
 * s'associent librement a une operation, plusieurs a la fois.
 */
final class Tag
{
    /**
     * Couleur par defaut quand l'utilisateur n'en choisit pas.
     */
    public const DEFAULT_COLOR = '#64748B';

    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $color,
        /**
         * Nombre d'operations portant ce tag.
         * Renseigne uniquement par les requetes qui le calculent.
         */
        public readonly int $usageCount = 0,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['color'],
            isset($row['usage_count']) ? (int) $row['usage_count'] : 0,
        );
    }

    /**
     * Couleur de texte lisible sur le fond du tag.
     *
     * On calcule la luminance perceptuelle plutot que la moyenne des canaux :
     * l'oeil est bien plus sensible au vert qu'au bleu, et une simple moyenne
     * rendrait du texte blanc illisible sur un fond jaune vif. Les
     * coefficients sont ceux de la recommandation UIT-R BT.601.
     */
    public function readableTextColor(): string
    {
        $hex = ltrim($this->color, '#');

        if (strlen($hex) !== 6) {
            return '#ffffff';
        }

        $r = (int) hexdec(substr($hex, 0, 2));
        $g = (int) hexdec(substr($hex, 2, 2));
        $b = (int) hexdec(substr($hex, 4, 2));

        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminance > 0.6 ? '#1f2937' : '#ffffff';
    }
}
