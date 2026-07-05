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
     * @throws InvalidArgumentException
     */
    public static function create(string $value): self
    {
        try {
            return new self(new \DateTimeImmutable($value));
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
}
