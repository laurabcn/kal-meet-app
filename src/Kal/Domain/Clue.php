<?php

declare(strict_types=1);

namespace App\Kal\Domain;

use App\Kal\Domain\Exception\KalException;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\Locale;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use App\Shared\Domain\ValueObject\UlidValue;

final class Clue
{
    private function __construct(
        public private(set) readonly UlidValue $id,
        public private(set) readonly NonEmptyStringValue $name,
        public private(set) readonly ?NonEmptyStringValue $description,
        public private(set) readonly File $file,
        public private(set) readonly Meeting $meeting,
        public private(set) readonly Locale $locale,
        public private(set) readonly DateTime $startsOn,
        public private(set) readonly DateTime $endsOn,
        public private(set) readonly DateTime $updatedAt,
    ) {
    }

    /**
     * @throws KalException
     * @throws InvalidArgumentException
     */
    public static function create(
        UlidValue $id,
        NonEmptyStringValue $name,
        DateTime $startsOn,
        DateTime $endsOn,
        File $file,
        Meeting $meeting,
        Locale $locale,
        ?NonEmptyStringValue $description = null,
    ): self {
        self::guardAgainstInvalidDateRange($startsOn, $endsOn);
        self::guardMeetingWithinRange($meeting, $startsOn, $endsOn);

        return new self(
            $id,
            $name,
            $description,
            $file,
            $meeting,
            $locale,
            $startsOn,
            $endsOn,
            DateTime::now(),
        );
    }

    /**
     * Rebuilds a clue already persisted, preserving its id and `updatedAt`
     * instead of minting new ones as `create()` does.
     *
     * @throws KalException
     */
    public static function reconstitute(
        UlidValue $id,
        NonEmptyStringValue $name,
        ?NonEmptyStringValue $description,
        File $file,
        Meeting $meeting,
        Locale $locale,
        DateTime $startsOn,
        DateTime $endsOn,
        DateTime $updatedAt,
    ): self {
        self::guardAgainstInvalidDateRange($startsOn, $endsOn);
        self::guardMeetingWithinRange($meeting, $startsOn, $endsOn);

        return new self(
            $id,
            $name,
            $description,
            $file,
            $meeting,
            $locale,
            $startsOn,
            $endsOn,
            $updatedAt,
        );
    }

    /**
     * Una pista amb els escalars canviats. `Clue` és immutable, així que editar
     * és fer-ne una de nova conservant identitat, PDF i reunió; només es mou
     * `updatedAt`. Els invariants es tornen a comprovar sencers: un canvi de
     * dates pot deixar la reunió fora del rang.
     *
     * @throws KalException
     * @throws InvalidArgumentException
     */
    public function withDetails(
        NonEmptyStringValue $name,
        ?NonEmptyStringValue $description,
        DateTime $startsOn,
        DateTime $endsOn,
        Locale $locale,
    ): self {
        self::guardAgainstInvalidDateRange($startsOn, $endsOn);
        self::guardMeetingWithinRange($this->meeting, $startsOn, $endsOn);

        return new self(
            $this->id,
            $name,
            $description,
            $this->file,
            $this->meeting,
            $locale,
            $startsOn,
            $endsOn,
            DateTime::now(),
        );
    }

    /**
     * @throws KalException
     */
    private static function guardAgainstInvalidDateRange(DateTime $startsOn, DateTime $endsOn): void
    {
        if (!$endsOn->isAfter($startsOn)) {
            throw KalException::invalidDateRange();
        }
    }

    /**
     * La reunió d'una pista es fa mentre la pista és viva: el rang que mana és el
     * de la pista, no el del KAL (que ja la conté per l'invariant de l'arrel).
     *
     * @throws KalException
     */
    private static function guardMeetingWithinRange(Meeting $meeting, DateTime $startsOn, DateTime $endsOn): void
    {
        if ($meeting->scheduledAt->isBefore($startsOn) || $meeting->scheduledAt->isAfter($endsOn)) {
            throw KalException::meetingOutsideClueRange();
        }
    }
}
