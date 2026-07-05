<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidArgumentException;

final readonly class PositiveIntegerValue
{
    /**
     * @throws InvalidArgumentException
     */
    public function __construct(private int $value)
    {
        $this->guardAgainstNegative($value);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value() === $other->value();
    }

    /**
     * @throws InvalidArgumentException
     */
    private function guardAgainstNegative(int $value): void
    {
        if ($value <= 0) {
            throw InvalidArgumentException::negativeValue();
        }
    }
}
