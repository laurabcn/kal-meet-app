<?php

declare(strict_types=1);

namespace Tests\Kal\Domain\Mother;

use App\Kal\Domain\Clue;
use App\Kal\Domain\File;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;

final class ClueMother
{
    public static function create(
        ?DateTime $startsOn = null,
        ?DateTime $endsOn = null,
        ?File $file = null,
        ?string $name = null,
    ): Clue {
        return Clue::create(
            new NonEmptyStringValue($name ?? 'Round 1'),
            $startsOn ?? DateTime::create('2026-08-01 00:00:00'),
            $endsOn ?? DateTime::create('2026-08-08 00:00:00'),
            $file ?? FileMother::create(),
        );
    }
}
