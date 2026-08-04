<?php

declare(strict_types=1);

use App\User\Domain\Exception\UserException;
use App\User\Infrastructure\Security\AuthenticatedUserFinder;
use Tests\User\Domain\Mother\AuthenticatedUserMother;
use Tests\User\Domain\Mother\ExternalIdMother;
use Tests\User\Domain\Mother\UserIdMother;
use Tests\User\Infrastructure\Persistence\InMemoryUserRepository;

// L'adaptador que travessa la frontera entre l'autenticació (Shared, que parla
// de strings crus) i el context User (que parla de value objects). Porta una
// decisió que costa cara si es trenca i que fins ara no comprovava ningú: una
// caiguda de BD NO és "perfil no trobat".

beforeEach(function (): void {
    $this->repository = new InMemoryUserRepository();
    $this->finder = new AuthenticatedUserFinder($this->repository);
});

it('maps the stored profile to the identity that crosses the boundary', function (): void {
    $user = AuthenticatedUserMother::create(
        UserIdMother::fromString('01J5M6XQBR4GTYHN8KZXP0F1W2'),
        ExternalIdMother::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479'),
    );
    $this->repository->add($user);

    $identity = $this->finder->findByExternalId('f47ac10b-58cc-4372-a567-0e02b2c3d479');

    expect($identity?->id)->toBe('01J5M6XQBR4GTYHN8KZXP0F1W2')
        ->and($identity?->externalId)->toBe('f47ac10b-58cc-4372-a567-0e02b2c3d479');
});

it('returns null when no profile matches, which is a normal outcome', function (): void {
    $this->repository->add(AuthenticatedUserMother::create());

    expect($this->finder->findByExternalId('f47ac10b-58cc-4372-a567-0e02b2c3d479'))->toBeNull();
});

it('does not even query the repository for an empty external id', function (): void {
    expect($this->finder->findByExternalId(''))->toBeNull()
        ->and($this->repository->calls())->toBe(0);
});

it('lets a database failure propagate instead of passing it off as a missing profile', function (): void {
    // La que importa. Si aquí es tornés null, una caiguda de Postgres arribaria
    // al frontend com un `auth_profile_not_found`: un 401 que diu "el teu
    // perfil no existeix" quan el que passa és que la BD és a terra. Cara de
    // depurar i mentida per a la usuària. Ha d'acabar en 500.
    $this->repository->failWith(UserException::persistenceFailed(new RuntimeException('connection refused')));

    expect(fn () => $this->finder->findByExternalId('f47ac10b-58cc-4372-a567-0e02b2c3d479'))
        ->toThrow(UserException::class, 'Failed to load the user profile.');
});
