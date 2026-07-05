<?php

declare(strict_types=1);

namespace App\Shared\Application\Message;

use App\Shared\Domain\ValueObject\NonEmptyStringValue;

interface StorableEventInterface extends StorableMessageInterface, EventMessageInterface
{
    public function streamId(): NonEmptyStringValue;
}
