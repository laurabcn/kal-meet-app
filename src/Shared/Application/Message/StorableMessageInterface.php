<?php

declare(strict_types=1);

namespace App\Shared\Application\Message;

use App\Shared\Domain\ValueObject\NonEmptyStringValue;

interface StorableMessageInterface extends ExternalMessageInterface
{
    public static function boundedContext(): NonEmptyStringValue;
}
