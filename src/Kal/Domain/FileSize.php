<?php

declare(strict_types=1);

namespace App\Kal\Domain;

use App\Shared\Domain\Exception\InvalidArgumentException;

final readonly class FileSize
{
    private const int SIZE_MAX = 5 * 1024 * 1024; // 5MB

    /** @throws InvalidArgumentException */
    private function __construct(public int $value)
    {
        $this->guardPositive($value);
        $this->guardNotExceed($value);
    }

    /** @throws InvalidArgumentException */
    public static function create(int $value): self
    {
        return new self($value);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /** @throws InvalidArgumentException */
    private function guardPositive(int $value): void
    {
        if ($value <= 0) {
            throw InvalidArgumentException::fileSizeNotPositive();
        }
    }

    /** @throws InvalidArgumentException */
    private function guardNotExceed(int $value): void
    {
        if ($value > self::SIZE_MAX) {
            throw InvalidArgumentException::fileSizeExceeded($value, self::SIZE_MAX);
        }
    }

    /** @throws InvalidArgumentException */
    public static function createDefault(): self
    {
        return new self(self::SIZE_MAX - 1000);
    }
}
