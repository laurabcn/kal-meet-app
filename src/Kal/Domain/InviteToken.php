<?php

declare(strict_types=1);

namespace App\Kal\Domain;

use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Exception\KalStateException;

final readonly class InviteToken
{
    private function __construct(public private(set) string $value)
    {
    }

    /** @throws KalException */
    public static function fromString(string $value): self
    {
        if ('' === $value) {
            throw KalException::emptyInviteToken();
        }

        return new self($value);
    }

    public static function create(string $parseString): self
    {
        return new self($parseString);
    }

    /** @throws KalStateException */
    public static function generate(): self
    {
        try {
            $token = bin2hex(random_bytes(16));
        } catch (\Random\RandomException) {
            throw KalStateException::inviteTokenGenerationFailed();
        }

        return new self($token);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
