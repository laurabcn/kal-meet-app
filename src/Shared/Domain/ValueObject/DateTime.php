<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidArgumentException;

final class DateTime
{
    public const string FORMAT = 'Y-m-d H:i:s';
    public const string TIME_ZONE = 'UTC';

    final private function __construct(private \DateTimeImmutable $value)
    {
    }

    public function value(): string
    {
        return $this->value->setTimezone(new \DateTimeZone(self::TIME_ZONE))
            ->format(self::FORMAT);
    }

    /**
     * $timezone lets the caller declare which time zone a wall-clock string
     * (with no explicit offset) should be interpreted in — e.g. a Meeting's
     * own `timezone` field. When omitted, falls back to PHP's default
     * `\DateTimeImmutable` behaviour (the server default time zone), exactly
     * as before this parameter existed.
     *
     * @throws InvalidArgumentException
     */
    public static function create(string $value, ?string $timezone = null): self
    {
        try {
            $dateTimeZone = null !== $timezone ? new \DateTimeZone($timezone) : null;
        } catch (\DateInvalidTimeZoneException) {
            throw InvalidArgumentException::invalidTimeZone($timezone);
        }

        try {
            return new self(new \DateTimeImmutable($value, $dateTimeZone));
        } catch (\DateMalformedStringException) {
            throw InvalidArgumentException::invalidDateTimeFormat(self::FORMAT);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function now(): self
    {
        try {
            return new self(new \DateTimeImmutable('now', new \DateTimeZone(self::TIME_ZONE)));
        } catch (\DateMalformedStringException) {
            throw InvalidArgumentException::invalidDateTimeFormat(self::FORMAT);
        }
    }

    /**
     * Absolute-instant comparisons: they compare the underlying
     * `\DateTimeImmutable` values directly, which PHP resolves correctly
     * across time zones — never the UTC-formatted string from `value()`.
     */
    public function isAfter(self $other): bool
    {
        return $this->value > $other->value;
    }

    public function isBefore(self $other): bool
    {
        return $this->value < $other->value;
    }

    public function isAfterOrEqual(self $other): bool
    {
        return $this->value >= $other->value;
    }

    public function isBeforeOrEqual(self $other): bool
    {
        return $this->value <= $other->value;
    }

    public function equals(self $other): bool
    {
        return $this->value == $other->value;
    }
}
