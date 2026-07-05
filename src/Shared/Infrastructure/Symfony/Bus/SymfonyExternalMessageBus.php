<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Bus;

use App\Shared\Application\Message\ExternalMessageBusInterface;
use App\Shared\Application\Message\ExternalMessageInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class SymfonyExternalMessageBus implements ExternalMessageBusInterface
{
    public function __construct(
        private MessageBusInterface $externalMessageBus,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    public function publish(ExternalMessageInterface $externalMessage): void
    {
        $this->externalMessageBus->dispatch($externalMessage);
    }
}
