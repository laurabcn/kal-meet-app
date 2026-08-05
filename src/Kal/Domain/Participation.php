<?php

declare(strict_types=1);

namespace App\Kal\Domain;

use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\UlidValue;

final readonly class Participation
{
    private function __construct(
        public UlidValue $id,
        public UlidValue $kalId,
        public UlidValue $userId,
        public DateTime $joinedAt,
    ) {
    }

    /** @throws InvalidArgumentException */
    public static function create(UlidValue $id, UlidValue $kalId, UlidValue $userId): self
    {
        return new self($id, $kalId, $userId, DateTime::now());
    }
}
