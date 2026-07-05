<?php

declare(strict_types=1);

namespace App\Shared\Application\Message;

use App\Shared\Domain\ValueObject\NonEmptyStringValue;

interface StorableCommandInterface extends StorableMessageInterface, CommandMessageInterface
{
    public function streamId(): ?NonEmptyStringValue;
}
