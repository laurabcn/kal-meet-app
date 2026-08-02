<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Security\Exception;

/**
 * Fallada en obtenir el JWKS. Interna: no arriba mai al client tal qual, la
 * decideix `JwksKeyProvider` (claus cachejades o `auth_keys_unavailable`).
 */
final class JwksFetchFailedException extends \RuntimeException
{
}
