<?php

declare(strict_types=1);

use App\Kal\Domain\Exception\KalException;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Infrastructure\Symfony\EventSubscriber\ApiExceptionMapper;
use App\Shared\Infrastructure\Symfony\Security\Exception\MissingTokenException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

beforeEach(function (): void {
    $this->mapper = new ApiExceptionMapper();
});

it('maps domain invariants to 400 with the exception code', function (): void {
    $mapped = $this->mapper->map(KalException::invalidDateRange());

    expect($mapped)->not->toBeNull()
        ->and($mapped->statusCode)->toBe(Response::HTTP_BAD_REQUEST)
        ->and($mapped->errorCode)->toBe('kal_invalid_date_range');
});

it('maps persistence failures to 500 with the persistence code', function (): void {
    $mapped = $this->mapper->map(KalException::persistenceFailed(new RuntimeException('db down')));

    expect($mapped)->not->toBeNull()
        ->and($mapped->statusCode)->toBe(Response::HTTP_INTERNAL_SERVER_ERROR)
        ->and($mapped->errorCode)->toBe('kal_persistence_failed');
});

it('maps kal_already_exists to 409', function (): void {
    $mapped = $this->mapper->map(KalException::alreadyExists());

    expect($mapped)->not->toBeNull()
        ->and($mapped->statusCode)->toBe(Response::HTTP_CONFLICT)
        ->and($mapped->errorCode)->toBe('kal_already_exists');
});

it('maps shared InvalidArgumentException to invalid_argument', function (): void {
    $mapped = $this->mapper->map(InvalidArgumentException::invalidUlid());

    expect($mapped)->not->toBeNull()
        ->and($mapped->statusCode)->toBe(Response::HTTP_BAD_REQUEST)
        ->and($mapped->errorCode)->toBe('invalid_argument');
});

it('does not map authentication failures', function (): void {
    expect($this->mapper->map(new MissingTokenException('auth_token_missing')))->toBeNull();
});

it('does not map Symfony security AuthenticationException', function (): void {
    expect($this->mapper->map(new Symfony\Component\Security\Core\Exception\InsufficientAuthenticationException()))->toBeNull();
});

it('does not map Symfony HTTP exceptions', function (): void {
    expect($this->mapper->map(new MethodNotAllowedHttpException(['POST'])))->toBeNull();
});

it('maps unknown throwables to internal_error', function (): void {
    $mapped = $this->mapper->map(new RuntimeException('boom'));

    expect($mapped)->not->toBeNull()
        ->and($mapped->statusCode)->toBe(Response::HTTP_INTERNAL_SERVER_ERROR)
        ->and($mapped->errorCode)->toBe('internal_error');
});
