<?php

declare(strict_types=1);

use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\UlidValue;
use App\User\Domain\Exception\UserException;
use App\User\Infrastructure\Persistence\Hydrator\UserHydrator;
use Tests\User\Domain\Mother\AuthenticatedUserMother;
use Tests\User\Domain\Mother\ExternalIdMother;
use Tests\User\Domain\Mother\UserIdMother;

beforeEach(function (): void {
    $this->hydrator = new UserHydrator();
});

it('hydrates an authenticated user from a profiles row', function (): void {
    $id = UlidValue::generate()->value();
    $externalId = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';

    $user = $this->hydrator->hydrate([
        'id' => $id,
        'external_id' => $externalId,
    ]);

    expect($user->id->value())->toBe($id)
        ->and($user->externalId->value())->toBe($externalId);
});

it('round-trips through extract and hydrate', function (): void {
    $user = AuthenticatedUserMother::create(
        UserIdMother::random(),
        ExternalIdMother::fromString('sub-round-trip'),
    );

    $extracted = $this->hydrator->extract($user);
    $hydrated = $this->hydrator->hydrate($extracted);

    expect($extracted)->toBe([
        'id' => $user->id->value(),
        'external_id' => $user->externalId->value(),
    ])
        ->and($hydrated->id->value())->toBe($user->id->value())
        ->and($hydrated->externalId->value())->toBe($user->externalId->value());
});

it('fails when the stored id is not a ulid', function (): void {
    expect(fn () => $this->hydrator->hydrate([
        'id' => 'not-a-ulid',
        'external_id' => 'sub-1',
    ]))->toThrow(UserException::class, 'The stored profile id is invalid.');
});

it('fails when id is missing or not a string', function (): void {
    expect(fn () => $this->hydrator->hydrate([
        'external_id' => 'sub-1',
    ]))->toThrow(UserException::class, 'The stored profile id is invalid.');

    expect(fn () => $this->hydrator->hydrate([
        'id' => 123,
        'external_id' => 'sub-1',
    ]))->toThrow(UserException::class, 'The stored profile id is invalid.');
});

it('fails when external_id is missing or empty', function (): void {
    $id = UlidValue::generate()->value();

    expect(fn () => $this->hydrator->hydrate([
        'id' => $id,
    ]))->toThrow(UserException::class, 'The stored profile id is invalid.');

    expect(fn () => $this->hydrator->hydrate([
        'id' => $id,
        'external_id' => '   ',
    ]))->toThrow(UserException::class, 'The stored profile id is invalid.');
});

it('rejects extract of a non-authenticated-user object', function (): void {
    expect(fn () => $this->hydrator->extract(new stdClass()))
        ->toThrow(InvalidArgumentException::class, 'The value is not an AuthenticatedUser');
});
