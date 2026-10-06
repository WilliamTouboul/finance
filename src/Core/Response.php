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

            $this->sendSecurityHeaders();
        }

        echo $this->body;
    }

    /**
     * En-tetes de securite appliques a toutes les reponses.
     */
    private function sendSecurityHeaders(): void
    {
        // Empeche le navigateur de deviner un type de contenu : un fichier
        // servi en text/plain ne doit jamais finir interprete comme du HTML.
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');

        /*
         * Politique de securite du contenu.
         *
         * 'unsafe-inline' est necessaire : les couleurs de tags sont posees en
         * attribut style, et une confirmation de suppression en attribut
         * onsubmit. La politique garde malgre tout sa valeur, car elle bloque
         * le chargement de tout script ou feuille de style venus d'ailleurs --
         * ce qu'un XSS cherche justement a faire.
         *
         * form-action interdit qu'un formulaire soit detourne vers un autre
         * domaine, et base-uri qu'une balise <base> injectee ne reroute les
         * URL relatives de la page.
         */
        header(
            "Content-Security-Policy: default-src 'self'; "
            . "script-src 'self' 'unsafe-inline'; "
            . "style-src 'self' 'unsafe-inline'; "
            . "img-src 'self' data:; "
            . "form-action 'self'; "
            . "base-uri 'self'; "
            . "frame-ancestors 'none'"
        );

        /*
         * HSTS : une fois le site visite en HTTPS, le navigateur refusera
         * pendant un an de s'y connecter en clair, meme si l'utilisateur tape
         * l'adresse sans https. Cela ferme la fenetre ou une interception
         * pourrait rediriger la toute premiere requete.
         *
         * Pose uniquement quand la requete est effectivement chiffree : sur un
         * site encore servi en HTTP, l'en-tete le rendrait injoignable.
         */
        if (self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }

    /**
     * La requete est-elle arrivee en HTTPS ?
     *
     * Derriere un proxy, le serveur applicatif recoit du HTTP en clair et le
     * protocole d'origine n'est connu que par un en-tete. Celui-ci est
     * declaratif, donc falsifiable -- mais le seul effet d'une falsification
     * serait d'ajouter une protection supplementaire, jamais d'en retirer une.
     */
    private static function isHttps(): bool
    {
        if (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off') {
            return true;
        }

        return ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }
}
