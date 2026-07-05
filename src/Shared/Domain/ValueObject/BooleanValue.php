<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

readonly class BooleanValue
{
    public function __construct(private bool $value)
    {
    }

    public function value(): bool
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value() === $other->value();
    }

    public static function createAsTrue(): self
    {
        return new self(true);
    }

    public static function createAsFalse(): self
    {
        return new self(false);
    }

    public function isTrue(): bool
    {
        return $this->value();
    }

    public function isFalse(): bool
    {
        return !$this->value();
    }
}
