<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Persistence;

use App\User\Domain\AuthenticatedUser;
use App\User\Domain\Exception\UserException;
use App\User\Domain\ExternalId;
use App\User\Domain\UserRepositoryInterface;
use App\User\Infrastructure\Persistence\Hydrator\UserHydrator;
use Doctrine\DBAL\Connection;

final readonly class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        private Connection $connection,
        private UserHydrator $hydrator,
    ) {
    }

    /**
     * @throws UserException
     */
    public function findByExternalId(ExternalId $externalId): ?AuthenticatedUser
    {
        try {
            $row = $this->connection->fetchAssociative(
                'SELECT id, external_id FROM profiles WHERE external_id = :external_id',
                ['external_id' => $externalId->value()],
            );
        } catch (\Throwable $e) {
            throw UserException::persistenceFailed($e);
        }

        if (false === $row) {
            return null;
        }

        return $this->hydrator->hydrate($row);
    }
}
