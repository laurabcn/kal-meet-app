<?php

declare(strict_types=1);

namespace App\Kal\Domain;

use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Exception\KalStateException;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\Model\AggregateRoot;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use App\Shared\Domain\ValueObject\UlidValue;

final class Kal extends AggregateRoot
{
    private function __construct(
        public private(set) readonly UlidValue $id,
        public private(set) readonly UlidValue $organizerId,
        public private(set) NonEmptyStringValue $name,
        public private(set) ?NonEmptyStringValue $description,
        public private(set) readonly Files $files,
        public private(set) readonly Clues $clues,
        public private(set) readonly Locales $locales,
        public private(set) DateTime $startsOn,
        public private(set) ?DateTime $endsOn,
        public private(set) ?string $coverPath,
        public private(set) readonly InviteToken $inviteToken,
        public private(set) readonly Meetings $meetings,
        public private(set) readonly DebateRoom $debateRoom,
        public private(set) readonly DateTime $createdAt,
        public private(set) ?DateTime $updatedAt,
        public private(set) ?DateTime $deletedAt,
    ) {
    }

    /**
     * @throws KalException
     * @throws KalStateException
     * @throws InvalidArgumentException
     */
    public static function create(
        UlidValue $id,
        UlidValue $organizerId,
        NonEmptyStringValue $name,
        DateTime $startsOn,
        Locales $locales,
        Files $files,
        Clues $clues,
        ?NonEmptyStringValue $description = null,
        ?DateTime $endsOn = null,
        ?string $coverPath = null,
        ?Meetings $meetings = null,
    ): self {
        self::guardAgainstInvalidDateRange($startsOn, $endsOn);
        self::guardCluesWithinRange($clues, $startsOn, $endsOn);
        self::guardFilesLocaleEnabled($files, $locales);
        self::guardCluesFileLocaleEnabled($clues, $locales);
        self::guardCluesLocaleEnabled($clues, $locales);

        $now = DateTime::now();

        return new self(
            $id,
            $organizerId,
            $name,
            $description,
            $files,
            $clues,
            $locales,
            $startsOn,
            $endsOn,
            $coverPath,
            InviteToken::generate(),
            $meetings ?? Meetings::create([]),
            DebateRoom::create(),
            $now,
            null,
            null,
        );
    }

    /** @throws KalException */
    public static function reconstitute(
        UlidValue $id,
        UlidValue $organizerId,
        NonEmptyStringValue $name,
        ?NonEmptyStringValue $description,
        Files $files,
        Clues $clues,
        Locales $locales,
        DateTime $startsOn,
        ?DateTime $endsOn,
        ?string $coverPath,
        InviteToken $inviteToken,
        Meetings $meetings,
        DebateRoom $debateRoom,
        DateTime $createdAt,
        ?DateTime $updatedAt = null,
        ?DateTime $deletedAt = null,
    ): self {
        self::guardAgainstInvalidDateRange($startsOn, $endsOn);
        self::guardCluesWithinRange($clues, $startsOn, $endsOn);
        self::guardFilesLocaleEnabled($files, $locales);
        self::guardCluesFileLocaleEnabled($clues, $locales);
        self::guardCluesLocaleEnabled($clues, $locales);

        return new self(
            $id,
            $organizerId,
            $name,
            $description,
            $files,
            $clues,
            $locales,
            $startsOn,
            $endsOn,
            $coverPath,
            $inviteToken,
            $meetings,
            $debateRoom,
            $createdAt,
            $updatedAt,
            $deletedAt,
        );
    }

    /** @throws KalException */
    public function addClue(Clue $clue): void
    {
        self::guardClueWithinRange($clue, $this->startsOn, $this->endsOn);
        self::guardFileLocaleEnabled($clue->file, $this->locales);
        self::guardClueLocaleEnabled($clue, $this->locales);

        $this->clues->add($clue);
    }

    public function addMeeting(Meeting $meeting): void
    {
        $this->meetings->add($meeting);
    }

    /**
     * Replaces the editable scalar fields of the Kal. Caller passes the full
     * desired values (merge of current + PATCH changes).
     *
     * @throws KalException
     * @throws InvalidArgumentException
     */
    public function update(
        NonEmptyStringValue $name,
        ?NonEmptyStringValue $description,
        DateTime $startsOn,
        ?DateTime $endsOn,
        ?string $coverPath,
    ): void {
        self::guardAgainstInvalidDateRange($startsOn, $endsOn);
        self::guardCluesWithinRange($this->clues, $startsOn, $endsOn);

        $this->name = $name;
        $this->description = $description;
        $this->startsOn = $startsOn;
        $this->endsOn = $endsOn;
        $this->coverPath = $coverPath;
        $this->updatedAt = DateTime::now();
    }

    /** @throws InvalidArgumentException */
    public function delete(): void
    {
        $this->deletedAt = DateTime::now();
    }

    /** @throws KalException */
    private static function guardAgainstInvalidDateRange(DateTime $startsOn, ?DateTime $endsOn): void
    {
        if (null !== $endsOn && !$endsOn->isAfter($startsOn)) {
            throw KalException::invalidDateRange();
        }
    }

    /** @throws KalException */
    private static function guardCluesWithinRange(Clues $clues, DateTime $startsOn, ?DateTime $endsOn): void
    {
        foreach ($clues->all() as $clue) {
            self::guardClueWithinRange($clue, $startsOn, $endsOn);
        }
    }

    /** @throws KalException */
    private static function guardFilesLocaleEnabled(Files $files, Locales $locales): void
    {
        foreach ($files->all() as $file) {
            self::guardFileLocaleEnabled($file, $locales);
        }
    }

    /** @throws KalException */
    private static function guardCluesFileLocaleEnabled(Clues $clues, Locales $locales): void
    {
        foreach ($clues->all() as $clue) {
            self::guardFileLocaleEnabled($clue->file, $locales);
        }
    }

    /** @throws KalException */
    private static function guardFileLocaleEnabled(File $file, Locales $locales): void
    {
        if (!$locales->contains($file->locale)) {
            throw KalException::fileLocaleNotEnabled();
        }
    }

    /** @throws KalException */
    private static function guardCluesLocaleEnabled(Clues $clues, Locales $locales): void
    {
        foreach ($clues->all() as $clue) {
            self::guardClueLocaleEnabled($clue, $locales);
        }
    }

    /** @throws KalException */
    private static function guardClueLocaleEnabled(Clue $clue, Locales $locales): void
    {
        if (!$locales->contains($clue->locale)) {
            throw KalException::clueLocaleNotEnabled();
        }
    }

    /** @throws KalException */
    private static function guardClueWithinRange(Clue $clue, DateTime $startsOn, ?DateTime $endsOn): void
    {
        if ($clue->startsOn->isBefore($startsOn)) {
            throw KalException::clueOutsideKalRange();
        }

        if (null !== $endsOn && $clue->endsOn->isAfter($endsOn)) {
            throw KalException::clueOutsideKalRange();
        }
    }
}
