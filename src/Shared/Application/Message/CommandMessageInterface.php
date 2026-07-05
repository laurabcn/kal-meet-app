<?php

declare(strict_types=1);

namespace App\Shared\Application\Message;

interface CommandMessageInterface extends ExternalMessageInterface
{
    /**
     * @return array<string, mixed>
     */
    public function command(): array;
}
