<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Persistence\Hydrator;

use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\Repository\Hydrator\HydratorInterface;
use App\User\Domain\AuthenticatedUser;
use App\User\Domain\Exception\UserException;
use App\User\Domain\ExternalId;
use App\User\Domain\UserId;

/** @implements HydratorInterface<AuthenticatedUser> */
final readonly class UserHydrator implements HydratorInterface
{
    /**
     * @param array<string, mixed> $data
     *
     * @throws UserException
     */
    public function hydrate(array $data): AuthenticatedUser
    {
        try {
            return new AuthenticatedUser(
                UserId::create($this->parseString($data['id'] ?? null)),
                ExternalId::create($this->parseString($data['external_id'] ?? null)),
            );
        } catch (InvalidArgumentException $e) {
            throw UserException::invalidStoredProfileId($e);
        }
    }

    /**
     * @return array{id: string, external_id: string}
     *
     * @throws InvalidArgumentException
     */
    public function extract(object $object): array
    {
        if (!$object instanceof AuthenticatedUser) {
            throw InvalidArgumentException::notAnAuthenticatedUser();
        }

        return [
            'id' => $object->id->value(),
            'external_id' => $object->externalId->value(),
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    private function parseString(mixed $value): string
    {
        if (!\is_string($value)) {
            throw InvalidArgumentException::notAString();
        }

        return $value;
    }
}
