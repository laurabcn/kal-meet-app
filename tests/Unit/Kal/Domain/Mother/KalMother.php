<?php

declare(strict_types=1);

namespace Tests\Unit\Kal\Domain\Mother;

use App\Kal\Domain\Clues;
use App\Kal\Domain\Files;
use App\Kal\Domain\Kal;
use App\Kal\Domain\Locales;
use App\Kal\Domain\Meetings;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use App\Shared\Domain\ValueObject\UlidValue;

final class KalMother
{
    public static function create(
        ?UlidValue $id = null,
        ?Locales $locales = null,
        ?Files $files = null,
        ?Clues $clues = null,
        ?DateTime $startsOn = null,
        ?DateTime $endsOn = null,
        ?NonEmptyStringValue $description = null,
        ?string $coverPath = null,
        ?UlidValue $organizerId = null,
        ?NonEmptyStringValue $name = null,
        ?Meetings $meetings = null,
    ): Kal {
        return Kal::create(
            $id ?? UlidValue::generate(),
            $organizerId ?? UlidValue::generate(),
            $name ?? NonEmptyStringValue::create('Summer Shawl KAL'),
            $startsOn ?? DateTime::create('2026-08-01 00:00:00'),
            $locales ?? LocalesMother::catalanAndSpanish(),
            $files ?? FilesMother::empty(),
            $clues ?? CluesMother::empty(),
            $description,
            $endsOn,
            $coverPath,
            $meetings,
        );
    }
}
