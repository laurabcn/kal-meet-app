<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidArgumentException;

readonly class NonEmptyStringValue
{
    /**
     * @throws InvalidArgumentException
     */
    public function __construct(private string $value)
    {
        $this->guardAgainstEmptyString($value);
    }

    public function value(): string
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
    private function guardAgainstEmptyString(string $value): void
    {
        if ('' === trim($value)) {
            throw InvalidArgumentException::emptyValue();
        }
    }
}
