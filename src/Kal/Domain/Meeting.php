<?php

declare(strict_types=1);

namespace App\Kal\Domain;

use App\Kal\Domain\Exception\KalException;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\HttpsUrl;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use App\Shared\Domain\ValueObject\UlidValue;

final class Meeting
{
    public const string DEFAULT_TIMEZONE = 'Europe/Madrid';

    private function __construct(
        public private(set) readonly UlidValue $id,
        public private(set) readonly DateTime $scheduledAt,
        public private(set) readonly HttpsUrl $url,
        public private(set) readonly NonEmptyStringValue $title,
        public private(set) readonly string $timezone,
    ) {
    }

    /**
     * @throws KalException
     * @throws InvalidArgumentException
     */
    public static function create(
        DateTime $scheduledAt,
        HttpsUrl $url,
        NonEmptyStringValue $title,
        ?string $timezone = null,
    ): self {
        $tz = $timezone ?? self::DEFAULT_TIMEZONE;
        self::guardValidTimezone($tz);

        return new self(
            UlidValue::generate(),
            $scheduledAt,
            $url,
            $title,
            $tz,
        );
    }

    /** @throws KalException */
    public static function reconstitute(
        UlidValue $id,
        DateTime $scheduledAt,
        HttpsUrl $url,
        NonEmptyStringValue $title,
        string $timezone,
    ): self {
        self::guardValidTimezone($timezone);

        return new self($id, $scheduledAt, $url, $title, $timezone);
    }

    /**
     * @throws KalException
     */
    private static function guardValidTimezone(string $timezone): void
    {
        try {
            new \DateTimeZone($timezone);
        } catch (\DateInvalidTimeZoneException) {
            throw KalException::invalidMeetingTimezone();
        }
    }
}
