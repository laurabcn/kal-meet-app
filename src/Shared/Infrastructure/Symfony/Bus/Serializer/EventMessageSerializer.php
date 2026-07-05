<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Bus\Serializer;

use App\Shared\Application\Message\EventMessageInterface;

class EventMessageSerializer extends ExternalMessageSerializer
{
    protected function getMessageKey(): string
    {
        return 'event';
    }

    protected function getMessageClass(): string
    {
        return EventMessageInterface::class;
    }
}
