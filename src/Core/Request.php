<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Enveloppe immuable autour des superglobales.
 *
 * Aucun controleur ne lit $_GET / $_POST directement : tout passe par ici,
 * ce qui garde un seul point d'entree pour les donnees venant du client.
 */
final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     */
    private function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $query,
        private readonly array $body,
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';

        // Normalisation : pas de slash final, hors racine.
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        return new self($method, $path, $_GET, $_POST);
    }

    public function query(string $key, ?string $default = null): ?string
    {
        $value = $this->query[$key] ?? null;

        return is_string($value) ? $value : $default;
    }

    public function input(string $key, ?string $default = null): ?string
    {
        $value = $this->body[$key] ?? null;

        return is_string($value) ? trim($value) : $default;
    }

    /**
     * Valeurs multiples d'un champ de formulaire (cases a cocher, select multiple).
     *
     * @return array<int, string>
     */
    public function inputArray(string $key): array
    {
        $value = $this->body[$key] ?? null;

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }
}
