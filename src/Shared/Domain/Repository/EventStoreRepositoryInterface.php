<?php

declare(strict_types=1);

namespace App\Shared\Domain\Repository;

use App\Shared\Domain\Event\DomainEventInterface;
use App\Shared\Domain\ValueObject\UlidValue;

interface EventStoreRepositoryInterface
{
    public function register(DomainEventInterface ...$events): void;

    /** @return DomainEventInterface[] */
    public function getEvents(UlidValue $aggregateId): array;
}
