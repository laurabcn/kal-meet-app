<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Bus;

use App\Shared\Domain\Event\DomainEventBusInterface;
use App\Shared\Domain\Event\DomainEventInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class SymfonyDomainEventBus implements DomainEventBusInterface
{
    public function __construct(
        private MessageBusInterface $eventBus,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    public function publish(DomainEventInterface ...$events): void
    {
        foreach ($events as $event) {
            $this->eventBus->dispatch($event);
        }
    }
}
