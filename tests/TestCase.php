<?php

declare(strict_types=1);

namespace Tests;

/**
 * Socle de test minimal.
 *
 * Le projet n'a pas de dependance, donc pas de PHPUnit. Ces quelques
 * assertions suffisent a ce qui doit etre verifie ici : des calculs purs
 * (montants, dates, geometrie) dont une erreur passerait inapercue a l'oeil.
 *
 * Une suite est une classe heritant d'ici ; chaque methode commencant par
 * "test" est executee par bin/test.php.
 */
abstract class TestCase
{
    /** @var array<int, string> */
    public array $failures = [];

    public int $assertions = 0;

    /**
     * Nom lisible de la suite, affiche par le lanceur.
     */
    abstract public function name(): string;

    protected function assertSame(mixed $expected, mixed $actual, string $message): void
    {
        $this->assertions++;

        if ($expected !== $actual) {
            $this->failures[] = sprintf(
                '%s : attendu %s, obtenu %s',
                $message,
                $this->describe($expected),
                $this->describe($actual)
            );
        }
    }

    protected function assertTrue(bool $condition, string $message): void
    {
        $this->assertions++;

        if (!$condition) {
            $this->failures[] = $message . ' : la condition est fausse';
        }
    }

    protected function assertFalse(bool $condition, string $message): void
    {
        $this->assertTrue(!$condition, $message);
    }

    /**
     * Comparaison de flottants.
     *
     * Deux calculs mathematiquement egaux peuvent differer du dernier bit en
     * virgule flottante : exiger l'egalite stricte rendrait le test instable.
     */
    protected function assertApprox(float $expected, float $actual, string $message, float $tolerance = 0.01): void
    {
        $this->assertions++;

        if (abs($expected - $actual) > $tolerance) {
            $this->failures[] = sprintf('%s : attendu ~%s, obtenu %s', $message, $expected, $actual);
        }
    }

    private function describe(mixed $value): string
    {
        if (is_array($value)) {
            return '[' . implode(', ', array_map($this->describe(...), $value)) . ']';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_object($value)) {
            return get_class($value);
        }

        return (string) $value;
    }
}
