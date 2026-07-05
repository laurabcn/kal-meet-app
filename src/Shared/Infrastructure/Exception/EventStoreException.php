<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Exception;

final class EventStoreException extends \RuntimeException
{
    public static function canNotInsertEvents(string $message): self
    {
        return new self(sprintf('Could not register event(s) in the event store: %s', $message));
    }

    public static function canNotGetEvents(string $aggregateId): self
    {
        return new self(sprintf('Could not get events for aggregate with id %s', $aggregateId));
    }

    public static function invalidEventForHydration(): self
    {
        return new self('The event could not be hydrated');
    }
}
