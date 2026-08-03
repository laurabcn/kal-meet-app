<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\EventSubscriber;

use App\Shared\Domain\Exception\DomainException;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Infrastructure\Symfony\Security\Exception\AuthenticationFailedException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Excepció → status HTTP + codi d'error estable per al body `{"error":…}`.
 *
 * No mapeja auth/access (entry point / firewall) ni HttpException de Symfony
 * (405, 404 de ruta…).
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
            return new MappedHttpError(Response::HTTP_BAD_REQUEST, 'invalid_argument');
        }

        return new MappedHttpError(Response::HTTP_INTERNAL_SERVER_ERROR, 'internal_error');
    }

    private function mapDomainException(DomainException $exception): MappedHttpError
    {
        $code = $exception->getMessage();

        if (str_ends_with($code, '_persistence_failed')) {
            return new MappedHttpError(Response::HTTP_INTERNAL_SERVER_ERROR, $code);
        }

        if ('kal_already_exists' === $code) {
            return new MappedHttpError(Response::HTTP_CONFLICT, $code);
        }

        return new MappedHttpError(Response::HTTP_BAD_REQUEST, $code);
    }
}
