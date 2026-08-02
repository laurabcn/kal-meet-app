<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Security\Exception;

/** Cap capçalera `Authorization`, o n'hi ha una que no és `Bearer`. */
final class MissingTokenException extends AuthenticationFailedException
{
    public function errorCode(): string
    {
        return 'auth_token_missing';
    }
}
