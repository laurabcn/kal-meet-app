<?php

declare(strict_types=1);

namespace Tests\Unit\User\Domain\Mother;

use App\Shared\Domain\ValueObject\UlidValue;
use App\User\Domain\UserId;

final class UserIdMother
{
    public static function random(): UserId
    {
        return UserId::create(UlidValue::generate()->value());
    }

    public static function fromString(string $value): UserId
    {
        return UserId::create($value);
    }
}
