<?php

declare(strict_types=1);

namespace App\Shared\Application\Message;

interface ExternalMessageBusInterface
{
    public function publish(ExternalMessageInterface $externalMessage): void;
}
