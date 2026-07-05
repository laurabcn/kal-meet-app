<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidArgumentException;
use Symfony\Component\Uid\Ulid as SymfonyUlid;

readonly class UlidValue
{
    final protected function __construct(protected SymfonyUlid $value)
    {
    }

    /** @throws InvalidArgumentException */
    public static function create(string $value): static
    {
        try {
            return new static(SymfonyUlid::fromString($value));
        } catch (\InvalidArgumentException) {
            throw InvalidArgumentException::invalidUlid();
        }
    }

    /** @throws InvalidArgumentException */
    public static function generate(): self
    {
        try {
            $value = SymfonyUlid::generate();
        } catch (\InvalidArgumentException) {
            throw InvalidArgumentException::invalidTimestamp();
        }

        return self::create($value);
    }

    public function value(): string
    {
        return $this->value->__toString();
    }

    public function equals(self $other): bool
    {
        return $this->value() === $other->value();
    }

    /**
     * @throws InvalidArgumentException
     */
    public function toDateTime(): DateTime
    {
        return DateTime::create(
            $this->value->getDateTime()->format(DateTime::FORMAT),
        );
    }
}
