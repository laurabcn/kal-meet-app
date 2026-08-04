<?php

declare(strict_types=1);

namespace Tests\Unit\User\Domain\Mother;

use App\User\Domain\ExternalId;

final class ExternalIdMother
{
    public static function random(): ExternalId
    {
        return ExternalId::create(sprintf('%s-0000-4000-8000-%s', bin2hex(random_bytes(4)), bin2hex(random_bytes(6))));
    }

    public static function fromString(string $value): ExternalId
    {
        return ExternalId::create($value);
    }
}
