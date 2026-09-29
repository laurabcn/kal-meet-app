<?php

declare(strict_types=1);

namespace Tests\Unit\Kal\Domain\Mother;

use App\Kal\Domain\Clue;
use App\Kal\Domain\File;
use App\Kal\Domain\Meeting;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use App\Shared\Domain\ValueObject\UlidValue;

final class ClueMother
{
    public static function create(
        ?UlidValue $id = null,
        ?DateTime $startsOn = null,
        ?DateTime $endsOn = null,
        ?File $file = null,
        ?string $name = null,
        ?Meeting $meeting = null,
        ?NonEmptyStringValue $description = null,
    ): Clue {
        $file ??= FileMother::create();
        $startsOn ??= DateTime::create('2026-08-01 00:00:00');
        $endsOn ??= DateTime::create('2026-08-08 00:00:00');

        return Clue::create(
            $id ?? UlidValue::generate(),
            NonEmptyStringValue::create($name ?? 'Round 1'),
            $startsOn,
            $endsOn,
            $file,
            // La reunió per defecte cau al començament de la pista: així qualsevol
            // rang que passi el test compleix l'invariant sense haver-la de passar.
            $meeting ?? MeetingMother::create(scheduledAt: $startsOn),
            $description,
        );
    }
}
