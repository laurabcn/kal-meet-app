<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

final class InvalidArgumentException extends \InvalidArgumentException
{
    public static function negativeValue(): self
    {
        return new self('The value cannot be negative');
    }

    public static function notPositive(): self
    {
        return new self('The value must be greater than zero');
    }

    public static function emptyValue(): self
    {
        return new self('The value cannot be empty');
    }

    public static function invalidDateTimeFormat(string $format): self
    {
        return new self(sprintf('The date time does not match the expected format "%s"', $format));
    }

    public static function invalidUlid(): self
    {
        return new self('The value is not a valid ULID');
    }

    public static function invalidTimestamp(): self
    {
        return new self('The ULID timestamp is invalid');
    }
}
