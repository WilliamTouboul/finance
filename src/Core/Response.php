<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Reponse HTTP. Les controleurs en retournent une plutot que d'ecrire
 * directement dans la sortie : le front controller reste seul responsable
 * de l'envoi.
 */
final class Response
{
    /**
     * @param array<string, string> $headers
     */
    private function __construct(
        private readonly string $body,
        private readonly int $status,
        private readonly array $headers,
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }

            // En-tetes de securite appliques a toutes les reponses.
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('Referrer-Policy: same-origin');
        }

        echo $this->body;
    }
}
