<?php

declare(strict_types=1);

namespace Tests\User\Infrastructure\Persistence;

use App\User\Domain\AuthenticatedUser;
use App\User\Domain\Exception\UserException;
use App\User\Domain\ExternalId;
use App\User\Domain\UserRepositoryInterface;

/**
 * Doble del port per provar `AuthenticatedUserFinder` sense BD. Segueix les
 * mateixes dues regles que `InMemoryKalRepository`: sap fallar (el port declara
 * `@throws UserException`) i no assereix res que en producció visqui al SQL.
 *
 * `calls()` hi és per un cas concret: comprovar que un `external_id` buit ni
 * arriba a consultar-se.
 */
final class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var array<string, AuthenticatedUser> */
    private array $users = [];

    private ?UserException $failure = null;

    private int $calls = 0;

    public function add(AuthenticatedUser $user): void
    {
        $this->users[$user->externalId->value()] = $user;
    }

    public function failWith(UserException $failure): void
    {
        $this->failure = $failure;
    }

    public function calls(): int
    {
        return $this->calls;
    }

    /** @throws UserException */
    public function findByExternalId(ExternalId $externalId): ?AuthenticatedUser
    {
        ++$this->calls;

        if (null !== $this->failure) {
            throw $this->failure;
        }

        return $this->users[$externalId->value()] ?? null;
    }
}
