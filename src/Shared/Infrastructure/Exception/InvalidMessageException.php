<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Exception;

final class InvalidMessageException extends \InvalidArgumentException
{
    public static function missingBody(): self
    {
        return new self('The decoded event is missing the body.');
    }

    public static function invalidSerializer(): self
    {
        return new self('The message class is not compatible with the serializer.');
    }

    public static function missingMessageName(): self
    {
        return new self('The decoded event is missing the event name.');
    }
}
