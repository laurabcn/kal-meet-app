<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Security\Exception;

use Symfony\Component\HttpFoundation\Response;

/**
 * No hi ha cap clau amb què verificar: el JWKS no es pot baixar i la caché és
 * freda. No és culpa del client — per això 503 i no 401.
 */
final class VerificationKeysUnavailableException extends AuthenticationFailedException
{
    public function errorCode(): string
    {
        return 'auth_keys_unavailable';
    }

    public function errorMessage(): string
    {
        return 'Authentication keys are temporarily unavailable.';
    }

    public function statusCode(): int
    {
        return Response::HTTP_SERVICE_UNAVAILABLE;
    }
}
