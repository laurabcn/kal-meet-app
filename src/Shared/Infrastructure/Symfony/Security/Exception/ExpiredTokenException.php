<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Security\Exception;

/** Signatura correcta però `exp` ja passat: el client ha de refrescar, no reautenticar-se. */
final class ExpiredTokenException extends AuthenticationFailedException
{
    public function errorCode(): string
    {
        return 'auth_token_expired';
    }

    public function errorMessage(): string
    {
        return 'Authentication token has expired.';
    }
}
