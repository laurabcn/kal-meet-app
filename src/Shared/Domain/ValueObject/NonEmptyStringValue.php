<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidArgumentException;

readonly class NonEmptyStringValue
{
    /**
     * @throws InvalidArgumentException
     */
    final protected function __construct(public private(set) string $value)
    {
        $this->guardAgainstEmptyString($value);
    }

    /**
     * `static` i no `self`: sense això, `ExternalId::create()` tornaria un
     * `NonEmptyStringValue` i qualsevol paràmetre tipat amb la subclasse
     * petaria amb `TypeError`.
     *
     * @throws InvalidArgumentException
     */
    public static function create(string $value): static
    {
        return new static($value);
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
