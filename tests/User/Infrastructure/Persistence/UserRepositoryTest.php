<?php

declare(strict_types=1);

use App\Shared\Domain\ValueObject\UlidValue;
use App\User\Domain\Exception\UserException;
use App\User\Infrastructure\Persistence\Hydrator\UserHydrator;
use App\User\Infrastructure\Persistence\UserRepository;
use Doctrine\DBAL\DriverManager;
use Tests\User\Domain\Mother\ExternalIdMother;
use Tests\User\Infrastructure\Persistence\ProfilesFixture;

beforeEach(function (): void {
    $this->hydrator = new UserHydrator();
});

it('resolves the auth provider id to the internal ULID of the profile', function (): void {
    $connection = ProfilesFixture::connection();
    $internalId = UlidValue::generate()->value();
    ProfilesFixture::insert($connection, $internalId, 'f47ac10b-58cc-4372-a567-0e02b2c3d479');
    $repository = new UserRepository($connection, $this->hydrator);

    $user = $repository->findByExternalId(ExternalIdMother::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479'));

    expect($user)->not->toBeNull()
        ->and($user?->id->value())->toBe($internalId)
        ->and($user?->externalId->value())->toBe('f47ac10b-58cc-4372-a567-0e02b2c3d479');
});

it('returns null when no profile matches, which is a normal outcome', function (): void {
    $connection = ProfilesFixture::connection();
    ProfilesFixture::insert($connection, UlidValue::generate()->value(), 'someone-else');
    $repository = new UserRepository($connection, $this->hydrator);

    $user = $repository->findByExternalId(ExternalIdMother::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479'));

    expect($user)->toBeNull();
});

it('returns null on an empty profiles table', function (): void {
    $repository = new UserRepository(ProfilesFixture::connection(), $this->hydrator);

    expect($repository->findByExternalId(ExternalIdMother::random()))->toBeNull();
});

it('picks the profile of the given auth provider id and no other', function (): void {
    $connection = ProfilesFixture::connection();
    $wanted = UlidValue::generate()->value();
    ProfilesFixture::insert($connection, UlidValue::generate()->value(), 'sub-1');
    ProfilesFixture::insert($connection, $wanted, 'sub-2');
    ProfilesFixture::insert($connection, UlidValue::generate()->value(), 'sub-3');
    $repository = new UserRepository($connection, $this->hydrator);

    $user = $repository->findByExternalId(ExternalIdMother::fromString('sub-2'));

    expect($user?->id->value())->toBe($wanted);
});

it('fails with a code when the query cannot run', function (): void {
    // Connexió sense la taula `profiles`: un fallo de la BD no ha de sortir
    // com una excepció de Doctrine a través del port.
    $repository = new UserRepository(
        DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]),
        $this->hydrator,
    );

    expect(fn () => $repository->findByExternalId(ExternalIdMother::random()))
        ->toThrow(UserException::class, 'Failed to load the user profile.');
});

it('fails with a code when the stored profile id is not a ULID', function (): void {
    $connection = ProfilesFixture::connection();
    ProfilesFixture::insert($connection, 'not-a-ulid', 'sub-1');
    $repository = new UserRepository($connection, $this->hydrator);

    expect(fn () => $repository->findByExternalId(ExternalIdMother::fromString('sub-1')))
        ->toThrow(UserException::class, 'The stored profile id is invalid.');
});
