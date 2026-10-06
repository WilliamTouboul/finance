<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;

/**
 * Un utilisateur de l'application.
 *
 * Objet immuable (readonly) : une fois construit depuis la base, il ne peut
 * plus etre modifie par erreur au fil du traitement. Les modifications passent
 * par le repository, qui est le seul a ecrire.
 */
final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $displayName,
        public readonly string $passwordHash,
        /**
         * Compte de demonstration : cree a la volee pour un visiteur, detruit
         * a son expiration. Fonctionne en tout point comme un compte ordinaire.
         */
        public readonly bool $isDemo = false,
        public readonly ?DateTimeImmutable $expiresAt = null,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['email'],
            (string) $row['display_name'],
            (string) $row['password_hash'],
            (bool) ($row['is_demo'] ?? false),
            isset($row['expires_at']) && $row['expires_at'] !== null
                ? new DateTimeImmutable((string) $row['expires_at'])
                : null,
        );
    }

    /**
     * Heures restantes avant la destruction du compte de demonstration.
     */
    public function hoursBeforeExpiry(): ?int
    {
        if ($this->expiresAt === null) {
            return null;
        }

        $seconds = $this->expiresAt->getTimestamp() - time();

        return max(0, (int) ceil($seconds / 3600));
    }

    /**
     * Initiales pour l'affichage compact ("Will Touboul" -> "WT").
     */
    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->displayName)) ?: [];
        $letters = array_map(
            static fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)),
            array_slice($parts, 0, 2)
        );

        return implode('', $letters);
    }
}
