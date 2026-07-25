<?php

declare(strict_types=1);

namespace App\Kal\Domain;

use App\Kal\Domain\Exception\KalException;
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
        public private(set) readonly NonEmptyStringValue $name,
        public private(set) readonly ?NonEmptyStringValue $description,
        public private(set) readonly Files $files,
        public private(set) readonly Clues $clues,
        public private(set) readonly Locales $locales,
        public private(set) readonly DateTime $startsOn,
        public private(set) readonly ?DateTime $endsOn,
        public private(set) readonly ?string $coverPath,
        public private(set) readonly DateTime $createdAt,
        public private(set) readonly DateTime $updatedAt,
    ) {
    }

    /**
     * @throws KalException
     * @throws InvalidArgumentException
     */
    public static function create(
        UlidValue $organizerId,
        NonEmptyStringValue $name,
        DateTime $startsOn,
        Locales $locales,
        Files $files,
        Clues $clues,
        ?NonEmptyStringValue $description = null,
        ?DateTime $endsOn = null,
        ?string $coverPath = null,
    ): self {
        self::guardAgainstInvalidDateRange($startsOn, $endsOn);
        self::guardCluesWithinRange($clues, $startsOn, $endsOn);
        self::guardFilesLocaleEnabled($files, $locales);
        self::guardCluesFileLocaleEnabled($clues, $locales);

        $now = DateTime::now();

        return new self(
            UlidValue::generate(),
            $organizerId,
            $name,
            $description,
            $files,
            $clues,
            $locales,
            $startsOn,
            $endsOn,
            $coverPath,
            $now,
            $now,
        );
    }

    /**
     * @throws KalException
     */
    public function addClue(Clue $clue): void
    {
        self::guardClueWithinRange($clue, $this->startsOn, $this->endsOn);
        self::guardFileLocaleEnabled($clue->file(), $this->locales);

        $this->clues->add($clue);
    }

    public function id(): UlidValue
    {
        return $this->id;
    }

    public function organizerId(): UlidValue
    {
        return $this->organizerId;
    }

    public function name(): NonEmptyStringValue
    {
        return $this->name;
    }

    public function description(): ?NonEmptyStringValue
    {
        return $this->description;
    }

    public function startsOn(): DateTime
    {
        return $this->startsOn;
    }

    public function endsOn(): ?DateTime
    {
        return $this->endsOn;
    }

    public function coverPath(): ?string
    {
        return $this->coverPath;
    }

    public function createdAt(): DateTime
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    public function clues(): Clues
    {
        return $this->clues;
    }

    public function locales(): Locales
    {
        return $this->locales;
    }

    /**
     * @throws KalException
     */
    private static function guardAgainstInvalidDateRange(DateTime $startsOn, ?DateTime $endsOn): void
    {
        if (null !== $endsOn && !$endsOn->isAfter($startsOn)) {
            throw KalException::invalidDateRange();
        }
    }

    /**
     * @throws KalException
     */
    private static function guardCluesWithinRange(Clues $clues, DateTime $startsOn, ?DateTime $endsOn): void
    {
        foreach ($clues->all() as $clue) {
            self::guardClueWithinRange($clue, $startsOn, $endsOn);
        }
    }

    /**
     * @throws KalException
     */
    private static function guardFilesLocaleEnabled(Files $files, Locales $locales): void
    {
        foreach ($files->all() as $file) {
            self::guardFileLocaleEnabled($file, $locales);
        }
    }

    /**
     * @throws KalException
     */
    private static function guardCluesFileLocaleEnabled(Clues $clues, Locales $locales): void
    {
        foreach ($clues->all() as $clue) {
            self::guardFileLocaleEnabled($clue->file(), $locales);
        }
    }

    /**
     * @throws KalException
     */
    private static function guardFileLocaleEnabled(File $file, Locales $locales): void
    {
        if (!$locales->contains($file->locale)) {
            throw KalException::fileLocaleNotEnabled();
        }
    }

    /**
     * @throws KalException
     */
    private static function guardClueWithinRange(Clue $clue, DateTime $startsOn, ?DateTime $endsOn): void
    {
        if ($clue->startsOn()->isBefore($startsOn)) {
            throw KalException::clueOutsideKalRange();
        }

        if (null !== $endsOn && $clue->endsOn()->isAfter($endsOn)) {
            throw KalException::clueOutsideKalRange();
        }
    }
}
