<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Persistence;

use App\Shared\Domain\Exception\InvalidArgumentException;
use App\User\Domain\AuthenticatedUser;
use App\User\Domain\Exception\UserException;
use App\User\Domain\ExternalId;
use App\User\Domain\UserId;
use App\User\Domain\UserRepositoryInterface;
use Doctrine\DBAL\Connection;

final readonly class DbalUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @throws UserException
     */
    public function findByExternalId(ExternalId $externalId): ?AuthenticatedUser
    {
        try {
            $id = $this->connection->fetchOne(
                'SELECT id FROM profiles WHERE external_id = :external_id',
                ['external_id' => $externalId->value()],
            );
        } catch (\Throwable $e) {
            throw UserException::persistenceFailed($e);
        }

        if (false === $id) {
            return null;
        }

        return new AuthenticatedUser($this->toUserId($id), $externalId);
    }

    /**
     * @throws UserException
     */
    private function toUserId(mixed $id): UserId
    {
        if (!is_string($id)) {
            throw UserException::invalidStoredProfileId();
        }

        try {
            return UserId::create($id);
        } catch (InvalidArgumentException $e) {
            throw UserException::invalidStoredProfileId($e);
        }
    }
}
