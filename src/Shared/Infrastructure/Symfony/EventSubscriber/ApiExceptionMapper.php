<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\EventSubscriber;

use App\Shared\Domain\Exception\ConflictException;
use App\Shared\Domain\Exception\CorruptedStateException;
use App\Shared\Domain\Exception\DomainException;
use App\Shared\Domain\Exception\ForbiddenException;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Exception\UnauthorizedException;
use App\Shared\Infrastructure\Symfony\Security\Exception\AuthenticationFailedException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Excepció → status HTTP + cos `{"error":"<missatge>","code":"<codi>"}`.
 *
 * El status ve del tipus (`instanceof`), mai del text del missatge.
 * Auth/access i HttpException de Symfony retornen `null` (entry point / firewall).
 */
final class ApiExceptionMapper
{
    public function map(\Throwable $exception): ?MappedHttpError
    {
        if ($exception instanceof AuthenticationFailedException
            || $exception instanceof AuthenticationException
            || $exception instanceof AccessDeniedException
        ) {
            return null;
        }

        if ($exception instanceof HttpExceptionInterface) {
            return null;
        }

        if ($exception instanceof DomainException) {
            return $this->mapDomainException($exception);
        }

        if ($exception instanceof InvalidArgumentException) {
            return new MappedHttpError(
                Response::HTTP_BAD_REQUEST,
                $exception->errorCode(),
                $exception->getMessage(),
            );
        }

        if ($exception instanceof UnauthorizedException) {
            return new MappedHttpError(
                Response::HTTP_UNAUTHORIZED,
                'unauthorized',
                '' !== $exception->getMessage() ? $exception->getMessage() : 'Unauthorized.',
            );
        }

        if ($exception instanceof ForbiddenException) {
            return new MappedHttpError(
                Response::HTTP_FORBIDDEN,
                'forbidden',
                '' !== $exception->getMessage() ? $exception->getMessage() : ForbiddenException::ACCESS_DENIED,
            );
        }

        return new MappedHttpError(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'internal_error',
            'An internal error occurred.',
        );
    }

    private function mapDomainException(DomainException $exception): MappedHttpError
    {
        $code = $exception->errorCode();
        $message = $exception->getMessage();

        if ($exception instanceof NotFoundException) {
            return new MappedHttpError(Response::HTTP_NOT_FOUND, $code, $message);
        }

        if ($exception instanceof ConflictException) {
            return new MappedHttpError(Response::HTTP_CONFLICT, $code, $message);
        }

        // Per TIPUS i no pel text del codi (CLAUDE.md). El que arriba aquí com a
        // CorruptedStateException no és culpa de qui ha fet la petició, i el 500
        // és també el que fa que ApiExceptionSubscriber ho logui i ho alerti.
        if ($exception instanceof CorruptedStateException) {
            return new MappedHttpError(Response::HTTP_INTERNAL_SERVER_ERROR, $code, $message);
        }

        return new MappedHttpError(Response::HTTP_BAD_REQUEST, $code, $message);
    }
}
