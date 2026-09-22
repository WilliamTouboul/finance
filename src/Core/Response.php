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

    /**
     * Fichier CSV propose au telechargement.
     *
     * Le nom de fichier est nettoye avant d'entrer dans l'en-tete : un retour
     * chariot ou un guillemet glisse dedans permettrait d'injecter un en-tete
     * HTTP supplementaire. Il est ici construit par l'application, mais la
     * regle vaut pour tout ce qui finit dans un en-tete.
     */
    public static function csv(string $body, string $filename): self
    {
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '', $filename) ?: 'export.csv';

        return new self($body, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $safeName . '"',
            // Un export de donnees personnelles n'a rien a faire dans un cache.
            'Cache-Control'       => 'no-store',
        ]);
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
