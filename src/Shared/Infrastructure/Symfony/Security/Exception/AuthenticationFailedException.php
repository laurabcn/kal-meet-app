<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Security\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Base de les fallades d'autenticació que sí que sap distingir el frontend.
 *
 * Symfony col·lapsa qualsevol fallada de l'autenticador `access_token` en una
 * `BadCredentialsException`: caducat i invàlid queden indistingibles. Les
 * subclasses d'aquí porten el codi i l'estat de la taula de §3.5 del spec, i
 * `SupabaseAuthenticationEntryPoint` és qui els converteix en `{"error": ...}`.
 */
abstract class AuthenticationFailedException extends AuthenticationException
{
    abstract public function errorCode(): string;

    public function statusCode(): int
    {
        return Response::HTTP_UNAUTHORIZED;
    }

    public function getMessageKey(): string
    {
        return $this->errorCode();
    }
}
