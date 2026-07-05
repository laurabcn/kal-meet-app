<?php

declare(strict_types=1);

namespace App\Shared\Application\Message;

interface EventMessageInterface extends ExternalMessageInterface
{
    /**
     * @return array<string, mixed>
     */
    public function event(): array;
}
