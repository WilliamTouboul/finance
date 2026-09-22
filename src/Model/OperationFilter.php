<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Money;
use App\Core\Period;
use App\Core\Request;

/**
 * Criteres de recherche d'operations.
 *
 * Regroupes dans un objet plutot que passes en six arguments : la signature du
 * repository reste lisible, et ajouter un critere plus tard ne casse aucun
 * appel existant.
 *
 * Tous les criteres voyagent dans l'URL. Une recherche est donc partageable,
 * navigable avec le bouton retour du navigateur, et le rechargement de page ne
 * la perd pas.
 */
final class OperationFilter
{
    public const DIRECTION_EXPENSE = 'depenses';
    public const DIRECTION_INCOME  = 'recettes';

    /**
     * @param array<int, int> $tagIds tags deja valides comme appartenant a l'utilisateur.
     */
    public function __construct(
        public readonly Period $period,
        public readonly string $search = '',
        public readonly array $tagIds = [],
        public readonly ?string $direction = null,
        public readonly ?int $minCents = null,
        public readonly ?int $maxCents = null,
    ) {
    }

    /**
     * Construit les criteres depuis la requete.
     *
     * Les identifiants de tags sont filtres par l'appelant avant d'arriver ici :
     * ils viennent du client et ne sont pas dignes de confiance.
     *
     * @param array<int, int> $allowedTagIds
     */
    public static function fromRequest(Request $request, array $allowedTagIds): self
    {
        $direction = $request->query('sens');

        if (!in_array($direction, [self::DIRECTION_EXPENSE, self::DIRECTION_INCOME], true)) {
            $direction = null;
        }

        return new self(
            Period::fromParam($request->query('p')),
            mb_substr(trim((string) $request->query('q', '')), 0, 100),
            $allowedTagIds,
            $direction,
            self::parseAmount($request->query('min')),
            self::parseAmount($request->query('max')),
        );
    }

    /**
     * Un critere autre que la periode est-il actif ?
     * Sert a proposer une remise a zero seulement quand elle a un sens.
     */
    public function hasCriteria(): bool
    {
        return $this->search !== ''
            || $this->tagIds !== []
            || $this->direction !== null
            || $this->minCents !== null
            || $this->maxCents !== null;
    }

    /**
     * Les criteres sous forme de parametres d'URL, periode comprise.
     */
    public function toQueryString(): string
    {
        $params = ['p' => $this->period->toParam()];

        if ($this->search !== '') {
            $params['q'] = $this->search;
        }

        if ($this->direction !== null) {
            $params['sens'] = $this->direction;
        }

        if ($this->minCents !== null) {
            $params['min'] = Money::toInput($this->minCents);
        }

        if ($this->maxCents !== null) {
            $params['max'] = Money::toInput($this->maxCents);
        }

        $query = http_build_query($params);

        // Les tags sont ajoutes a part : http_build_query nommerait les
        // entrees tags[0], tags[1]... la ou tags[] suffit et reste lisible.
        foreach ($this->tagIds as $id) {
            $query .= '&tags%5B%5D=' . $id;
        }

        return $query;
    }

    /**
     * Les memes criteres sur une autre periode, pour la navigation temporelle.
     */
    public function withPeriod(Period $period): self
    {
        return new self(
            $period,
            $this->search,
            $this->tagIds,
            $this->direction,
            $this->minCents,
            $this->maxCents,
        );
    }

    private static function parseAmount(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $cents = Money::parse($value);

        return $cents === null ? null : abs($cents);
    }
}
