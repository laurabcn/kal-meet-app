<?php

declare(strict_types=1);

use App\Kal\Domain\Exception\KalAlreadyExistsException;
use App\Kal\Domain\Exception\KalAlreadyMemberException;
use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Exception\KalNotFoundException;
use App\Kal\Domain\Exception\KalStateException;
use App\Shared\Domain\Exception\ConflictException;
use App\Shared\Domain\Exception\CorruptedStateException;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Infrastructure\Symfony\EventSubscriber\ApiExceptionMapper;
use App\Shared\Infrastructure\Symfony\Security\Exception\MissingTokenException;
use App\User\Domain\Exception\UserException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

beforeEach(function (): void {
    $this->mapper = new ApiExceptionMapper();
});

it('maps domain invariants to 400 with message and stable code', function (): void {
    $exception = KalException::invalidDateRange();
    $mapped = $this->mapper->map($exception);

    expect($mapped)->not->toBeNull()
        ->and($mapped->statusCode)->toBe(Response::HTTP_BAD_REQUEST)
        ->and($mapped->errorCode)->toBe('kal_invalid_date_range')
        ->and($mapped->message)->toBe('The kal end date must be after the start date.');
});

it('maps persistence failures to 500 with message and stable code', function (
    CorruptedStateException $exception,
    string $code,
    string $message,
): void {
    $mapped = $this->mapper->map($exception);

    expect($mapped)->not->toBeNull()
        ->and($mapped->statusCode)->toBe(Response::HTTP_INTERNAL_SERVER_ERROR)
        ->and($mapped->errorCode)->toBe($code)
        ->and($mapped->message)->toBe($message);
})->with([
    'kal' => [
        fn () => KalStateException::persistenceFailed(new RuntimeException('db down')),
        'kal_persistence_failed',
        'Failed to persist the kal.',
    ],
    // Regressió: sense CorruptedStateException a UserException, el canvi de
    // str_ends_with → instanceof tornava a silenciar els errors de perfil
    // (400, sense log ni Slack).
    'user' => [
        fn () => UserException::persistenceFailed(new RuntimeException('db down')),
        'user_persistence_failed',
        'Failed to load the user profile.',
    ],
]);

it('maps ConflictException to 409 using the type, not the message', function (ConflictException $exception, string $code, string $message): void {
    $mapped = $this->mapper->map($exception);

    expect($exception)->toBeInstanceOf(ConflictException::class)
        ->and($mapped)->not->toBeNull()
        ->and($mapped->statusCode)->toBe(Response::HTTP_CONFLICT)
        ->and($mapped->errorCode)->toBe($code)
        ->and($mapped->message)->toBe($message);
})->with([
    'already exists' => [
        KalAlreadyExistsException::create(),
        'kal_already_exists',
        'A kal with this id already exists.',
    ],
    'already member' => [
        KalAlreadyMemberException::create(),
        'kal_already_member',
        'User is already a member of this kal.',
    ],
]);

it('maps NotFoundException to 404 using the type, not the message', function (): void {
    $exception = KalNotFoundException::create();
    $mapped = $this->mapper->map($exception);

    expect($exception)->toBeInstanceOf(NotFoundException::class)
        ->and($mapped)->not->toBeNull()
        ->and($mapped->statusCode)->toBe(Response::HTTP_NOT_FOUND)
        ->and($mapped->errorCode)->toBe('kal_not_found')
        ->and($mapped->message)->toBe('Kal not found.');
});

it('maps shared InvalidArgumentException with its code and message', function (): void {
    $exception = InvalidArgumentException::invalidUlid();
    $mapped = $this->mapper->map($exception);

    expect($mapped)->not->toBeNull()
        ->and($mapped->statusCode)->toBe(Response::HTTP_BAD_REQUEST)
        ->and($mapped->errorCode)->toBe('invalid_ulid')
        ->and($mapped->message)->toBe('The value is not a valid ULID');
});

it('does not map authentication failures', function (): void {
    expect($this->mapper->map(new MissingTokenException()))->toBeNull();
});

it('does not map Symfony security AuthenticationException', function (): void {
    expect($this->mapper->map(new Symfony\Component\Security\Core\Exception\InsufficientAuthenticationException()))->toBeNull();
});

it('does not map Symfony HTTP exceptions', function (): void {
    expect($this->mapper->map(new MethodNotAllowedHttpException(['POST'])))->toBeNull();
});

it('maps unknown throwables to internal_error with a readable message', function (): void {
    $mapped = $this->mapper->map(new RuntimeException('boom'));

    expect($mapped)->not->toBeNull()
        ->and($mapped->statusCode)->toBe(Response::HTTP_INTERNAL_SERVER_ERROR)
        ->and($mapped->errorCode)->toBe('internal_error')
        ->and($mapped->message)->toBe('An internal error occurred.');
});

// El status el decideix el TIPUS, no el text del codi. Abans hi havia un
// `str_ends_with($code, '_persistence_failed')`, i per això tot el que era
// culpa del servidor sense aquell sufix sortia com a 400 — i, com que
// ApiExceptionSubscriber només registra a partir de 500, en silenci.
it('maps every corrupted-state error to 500, whatever its code', function (
    CorruptedStateException $exception,
    string $code,
): void {
    $mapped = $this->mapper->map($exception);

    expect($mapped)->not->toBeNull()
        ->and($mapped->statusCode)->toBe(Response::HTTP_INTERNAL_SERVER_ERROR)
        ->and($mapped->errorCode)->toBe($code);
})->with([
    'debate room gone' => [fn () => KalStateException::missingDebateRoom(), 'kal_debate_room_missing'],
    'clue with no meeting' => [fn () => KalStateException::missingClueMeeting(), 'kal_clue_meeting_missing'],
    'not a kal' => [fn () => KalStateException::invalidKal(), 'kal_invalid'],
    'no entropy' => [fn () => KalStateException::inviteTokenGenerationFailed(), 'kal_invite_token_generation_failed'],
    'invalid stored profile id' => [fn () => UserException::invalidStoredProfileId(), 'user_invalid_stored_profile_id'],
]);

// La contrapartida: el que SÍ que pot arreglar qui fa la petició segueix sent
// un 400, i no ha d'omplir el log ni Slack.
it('keeps client-fixable domain errors on 400', function (): void {
    $mapped = $this->mapper->map(KalException::noLocalesEnabled());

    expect($mapped)->not->toBeNull()
        ->and($mapped->statusCode)->toBe(Response::HTTP_BAD_REQUEST)
        ->and($mapped->errorCode)->toBe('kal_no_locales_enabled');
});
