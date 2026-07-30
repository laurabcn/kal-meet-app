<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidArgumentException;

final readonly class HttpsUrl extends Url
{
    /**
     * @throws InvalidArgumentException
     */
    public static function fromString(string $value): self
    {
        $instance = new self($value);
        $instance->assertHttps();

        return $instance;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function assertHttps(): void
    {
        $scheme = parse_url($this->value(), \PHP_URL_SCHEME);

        if (!\is_string($scheme) || 'https' !== strtolower($scheme)) {
            throw InvalidArgumentException::urlMustBeHttps();
        }
    }
}
