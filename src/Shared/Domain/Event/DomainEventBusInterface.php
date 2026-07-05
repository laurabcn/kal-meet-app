<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

interface DomainEventBusInterface
{
    /**
     * Publishes a domain event to internal subscribers.
     */
    public function publish(DomainEventInterface ...$events): void;
}
