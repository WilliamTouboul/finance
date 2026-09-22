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

        return is_string($value) ? self::toUtf8($value) : $default;
    }

    public function input(string $key, ?string $default = null): ?string
    {
        $value = $this->body[$key] ?? null;

        return is_string($value) ? trim(self::toUtf8($value)) : $default;
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

        return array_values(array_map(
            self::toUtf8(...),
            array_filter($value, 'is_string')
        ));
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /**
     * Garantit que toute valeur entrante est de l'UTF-8 valide.
     *
     * Deux raisons, l'une pratique et l'autre de securite.
     *
     * Pratique : la base est en utf8mb4 et refuse categoriquement un octet
     * invalide (erreur MySQL 1366), ce qui transforme une simple saisie
     * accentuee venue d'un client mal configure en erreur serveur. Les
     * exports CSV des banques sont d'ailleurs souvent en Windows-1252.
     *
     * Securite : une sequence UTF-8 malformee est un moyen classique de
     * faire passer un caractere devant un filtre qui ne le reconnait pas,
     * pour le voir reinterprete plus loin dans la chaine de traitement.
     * Normaliser des l'entree ferme cette porte.
     *
     * Windows-1252 est le repli choisi : c'est de loin l'encodage non-UTF-8
     * le plus repandu pour du texte latin, et il couvre tout Latin-1.
     */
    private static function toUtf8(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $converted = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');

        // Si meme la conversion ne donne pas de l'UTF-8 valide, on retire les
        // octets fautifs plutot que de propager une chaine douteuse.
        return mb_check_encoding($converted, 'UTF-8')
            ? $converted
            : (string) mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }
}
