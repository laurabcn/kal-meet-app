<?php

declare(strict_types=1);

namespace Tests\Kal\Domain\Mother;

use App\Kal\Domain\Meeting;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\HttpsUrl;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;

final class MeetingMother
{
    public static function create(
        ?DateTime $scheduledAt = null,
        ?HttpsUrl $url = null,
        ?NonEmptyStringValue $title = null,
        ?string $timezone = null,
    ): Meeting {
        return Meeting::create(
            $scheduledAt ?? DateTime::create('2026-08-15 18:00:00', 'Europe/Madrid'),
            $url ?? HttpsUrl::fromString('https://zoom.us/j/123456789'),
            $title ?? NonEmptyStringValue::create('Weekly catch-up'),
            $timezone,
        );
    }
}
