<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Repository;

use App\Shared\Domain\Repository\Hydrator\HydratorInterface;
use Doctrine\DBAL\Connection;

final readonly class MySQLRepository
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @template T of object
     *
     * @param array<array<string, mixed>> $data
     * @param HydratorInterface<T>        $hydrator
     *
     * @return array<T>
     */
    public function hydrate(array $data, HydratorInterface $hydrator): array
    {
        $entities = [];

        /** @var array<string,mixed> $entity */
        foreach ($data as $entity) {
            /** @var T $hydrated */
            $hydrated = $hydrator->hydrate($entity);
            $entities[] = $hydrated;
        }

        return $entities;
    }

    public function connection(): Connection
    {
        return $this->connection;
    }
}
