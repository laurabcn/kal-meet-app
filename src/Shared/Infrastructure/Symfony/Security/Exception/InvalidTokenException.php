<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Security\Exception;

/**
 * Token malformat, amb la signatura tocada, o emès per un altre projecte
 * (`iss`/`aud` diferents dels configurats).
 */
final class InvalidTokenException extends AuthenticationFailedException
{
    public function errorCode(): string
    {
        return 'auth_token_invalid';
    }
}
