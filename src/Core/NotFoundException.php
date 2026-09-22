<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Levee quand aucune route ne correspond, ou qu'une ressource demandee
 * n'existe pas (ou n'appartient pas a l'utilisateur connecte).
 */
final class NotFoundException extends RuntimeException
{
}
