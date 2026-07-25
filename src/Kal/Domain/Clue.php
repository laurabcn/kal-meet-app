<?php

declare(strict_types=1);

namespace App\Kal\Domain;

use App\Kal\Domain\Exception\KalException;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use App\Shared\Domain\ValueObject\UlidValue;

final class Clue
{
    private function __construct(
        public private(set) readonly UlidValue $id,
        public private(set) readonly NonEmptyStringValue $name,
        public private(set) readonly ?NonEmptyStringValue $description,
        public private(set) readonly File $file,
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
        NonEmptyStringValue $name,
        DateTime $startsOn,
        DateTime $endsOn,
        File $file,
        ?NonEmptyStringValue $description = null,
    ): self {
        self::guardAgainstInvalidDateRange($startsOn, $endsOn);

        return new self(
            UlidValue::generate(),
            $name,
            $description,
            $file,
            $startsOn,
            $endsOn,
            DateTime::now(),
        );
    }

    public function id(): UlidValue
    {
        return $this->id;
    }

    public function name(): NonEmptyStringValue
    {
        return $this->name;
    }

    public function description(): ?NonEmptyStringValue
    {
        return $this->description;
    }

    public function file(): File
    {
        return $this->file;
    }

    public function startsOn(): DateTime
    {
        return $this->startsOn;
    }

    public function endsOn(): DateTime
    {
        return $this->endsOn;
    }

    public function updatedAt(): DateTime
    {
        return $this->updatedAt;
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
}
