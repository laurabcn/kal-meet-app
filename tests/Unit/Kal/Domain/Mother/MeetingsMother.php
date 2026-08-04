<?php

declare(strict_types=1);

namespace Tests\Unit\Kal\Domain\Mother;

use App\Kal\Domain\Meeting;
use App\Kal\Domain\Meetings;

final class MeetingsMother
{
    public static function empty(): Meetings
    {
        return Meetings::create([]);
    }

    public static function of(Meeting ...$meetings): Meetings
    {
        return Meetings::create($meetings);
    }
}
