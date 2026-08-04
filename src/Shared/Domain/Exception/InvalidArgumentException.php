<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

final class InvalidArgumentException extends \InvalidArgumentException
{
    public function __construct(
        private readonly string $errorCode,
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public static function invalidPayload(): self
    {
        return new self('invalid_payload', 'The request payload is invalid.');
    }

    public static function invalidJson(): self
    {
        return new self('invalid_json', 'The request body is not valid JSON.');
    }

    public static function negativeValue(): self
    {
        return new self('value_negative', 'The value cannot be negative');
    }

    public static function notPositive(): self
    {
        return new self('value_not_positive', 'The value must be greater than zero');
    }

    public static function emptyValue(): self
    {
        return new self('value_empty', 'The value cannot be empty');
    }

    public static function invalidDateTimeFormat(string $format): self
    {
        return new self(
            'invalid_datetime_format',
            sprintf('The date time does not match the expected format "%s"', $format),
        );
    }

    public static function invalidTimeZone(string $timezone): self
    {
        return new self(
            'invalid_timezone',
            sprintf('The value "%s" is not a valid time zone identifier', $timezone),
        );
    }

    public static function invalidUlid(): self
    {
        return new self('invalid_ulid', 'The value is not a valid ULID');
    }

    public static function invalidTimestamp(): self
    {
        return new self('invalid_ulid_timestamp', 'The ULID timestamp is invalid');
    }

    public static function fileSizeNotPositive(): self
    {
        return new self('file_size_not_positive', 'The file size must be greater than zero.');
    }

    public static function fileSizeExceeded(int $value, float|int $SIZE_MAX): self
    {
        return new self(
            'file_size_exceeded',
            sprintf('The file size %d exceeds the maximum allowed size of %d bytes', $value, $SIZE_MAX),
        );
    }

    public static function invalidLocale(string $value): self
    {
        return new self(
            'invalid_locale',
            sprintf('The value "%s" is not a valid locale', $value),
        );
    }

    public static function invalidUrl(): self
    {
        return new self('invalid_url', 'The value is not a valid URL.');
    }

    public static function urlMustBeHttps(): self
    {
        return new self('url_must_be_https', 'The URL must use HTTPS.');
    }

    public static function notAnInteger(): self
    {
        return new self('value_not_an_integer', 'The value is not an integer');
    }

    public static function notAString(): self
    {
        return new self('value_not_a_string', 'The value is not a string');
    }

    public static function authIdentityIncomplete(): self
    {
        return new self('auth_identity_incomplete', 'The authenticated identity is incomplete.');
    }
}
