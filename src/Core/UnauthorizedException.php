<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Levee quand une page protegee est demandee sans session valide.
 * Le front controller la transforme en redirection vers l'ecran de connexion.
 */
final class UnauthorizedException extends RuntimeException
{
}
