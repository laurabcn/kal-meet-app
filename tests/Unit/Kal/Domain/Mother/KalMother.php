<?php

declare(strict_types=1);

namespace Tests\Unit\Kal\Domain\Mother;

use App\Kal\Domain\Clues;
use App\Kal\Domain\File;
use App\Kal\Domain\Kal;
use App\Kal\Domain\Meetings;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\Locale;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use App\Shared\Domain\ValueObject\UlidValue;
use Tests\Unit\Shared\Domain\ValueObject\Mother\LocaleMother;

final class KalMother
{
    public static function create(
        ?UlidValue $id = null,
        ?Locale $locale = null,
        ?File $file = null,
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
            $locale ?? LocaleMother::catalan(),
            $clues ?? CluesMother::empty(),
            $file,
            $description,
            $endsOn,
            $coverPath,
            $meetings,
        );
    }
}
