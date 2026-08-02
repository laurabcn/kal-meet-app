<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Security;

use App\Shared\Infrastructure\Symfony\Security\Exception\AuthenticationFailedException;
use App\Shared\Infrastructure\Symfony\Security\Exception\MissingTokenException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * El punt únic que converteix una fallada d'autenticació en `{"error": "<codi>"}`.
 *
 * Fa dos papers perquè Symfony hi arriba per dos camins diferents, i cap dels
 * dos sol cobreix la taula de §3.5:
 *
 * - **Failure handler**: el token hi era però no val. `AccessTokenAuthenticator::
 *   onAuthenticationFailure()` retorna pel seu compte un 401 amb el cos BUIT si
 *   ningú li dona un failure handler; les excepcions diferenciades del
 *   `SupabaseTokenHandler` es perdrien just abans d'arribar al client.
 * - **Entry point**: no hi havia token. Sense capçalera `Bearer`, `supports()`
 *   retorna false, l'autenticador ni s'executa i qui rebutja la petició és
 *   l'`access_control` — una `InsufficientAuthenticationException` que no és
 *   cap de les nostres.
 */
final readonly class SupabaseAuthenticationEntryPoint implements AuthenticationEntryPointInterface, AuthenticationFailureHandlerInterface
{
    /** @throws \InvalidArgumentException */
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return self::toResponse($authException);
    }

    /** @throws \InvalidArgumentException */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return self::toResponse($exception);
    }

    /** @throws \InvalidArgumentException */
    private static function toResponse(?AuthenticationException $exception): JsonResponse
    {
        // Qualsevol fallada que no sigui de les nostres arriba per la via de
        // l'access_control, i allà l'única causa possible és que no hi hagués
        // token: si n'hi hagués hagut un de dolent, l'autenticador hauria petat
        // abans amb una excepció nostra.
        $failure = $exception instanceof AuthenticationFailedException
            ? $exception
            : new MissingTokenException('auth_token_missing');

        return new JsonResponse(['error' => $failure->errorCode()], $failure->statusCode());
    }
}
