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

    public static function invalidTimeZone(string $timezone): self
    {
        return new self(sprintf('The value "%s" is not a valid time zone identifier', $timezone));
    }

    public static function invalidUlid(): self
    {
        return new self('The value is not a valid ULID');
    }

    public static function invalidTimestamp(): self
    {
        return new self('The ULID timestamp is invalid');
    }

    public static function fileSizeNotPositive(): self
    {
        return new self('file_size_not_positive');
    }

    public static function fileSizeExceeded(int $value, float|int $SIZE_MAX): self
    {
        return new self(sprintf('The file size %d exceeds the maximum allowed size of %d bytes', $value, $SIZE_MAX));
    }

    public static function invalidLocale(string $value): self
    {
        return new self(sprintf('The value "%s" is not a valid locale', $value));
    }

    public static function invalidUrl(): self
    {
        return new self('invalid_url');
    }

    public static function urlMustBeHttps(): self
    {
        return new self('url_must_be_https');
    }
}
