<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidArgumentException;

readonly class Url
{
    /**
     * @throws InvalidArgumentException
     */
    final protected function __construct(protected string $value)
    {
        $this->guardValidUrl($value);
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function guardValidUrl(string $value): void
    {
        if ('' === trim($value)) {
            throw InvalidArgumentException::invalidUrl();
        }

        if (false === filter_var($value, \FILTER_VALIDATE_URL)) {
            throw InvalidArgumentException::invalidUrl();
        }

        $scheme = parse_url($value, \PHP_URL_SCHEME);

        if (!\is_string($scheme) || '' === $scheme) {
            throw InvalidArgumentException::invalidUrl();
        }
    }
}
