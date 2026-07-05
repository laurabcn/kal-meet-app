<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Bus\Serializer;

use App\Shared\Application\Message\CommandMessageInterface;

class CommandMessageSerializer extends ExternalMessageSerializer
{
    protected function getMessageKey(): string
    {
        return 'command';
    }

    protected function getMessageClass(): string
    {
        return CommandMessageInterface::class;
    }
}
