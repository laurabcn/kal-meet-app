<?php

declare(strict_types=1);

namespace App\Kal\Application\Command\Kal;

use App\Kal\Domain\Clue;
use App\Kal\Domain\Clues;
use App\Kal\Domain\Exception\KalAlreadyExistsException;
use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Exception\KalFileException;
use App\Kal\Domain\Exception\KalStateException;
use App\Kal\Domain\File;
use App\Kal\Domain\FileExtension;
use App\Kal\Domain\Files;
use App\Kal\Domain\FileSize;
use App\Kal\Domain\Kal;
use App\Kal\Domain\Locales;
use App\Kal\Domain\Meeting;
use App\Kal\Domain\Meetings;
use App\Kal\Domain\Repository\KalRepositoryInterface;
use App\Shared\Application\Command\CommandHandlerInterface;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\HttpsUrl;
use App\Shared\Domain\ValueObject\Locale;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use App\Shared\Domain\ValueObject\UlidValue;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateKalCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private KalRepositoryInterface $kalRepository,
    ) {
    }

    /**
     * @throws KalAlreadyExistsException
     * @throws KalException
     * @throws KalStateException
     * @throws InvalidArgumentException
     * @throws KalFileException
     */
    public function __invoke(CreateKalCommand $command): void
    {
        $kal = Kal::create(
            UlidValue::create($command->id),
            UlidValue::create($command->organizerId),
            NonEmptyStringValue::create($command->name),
            DateTime::create($command->startsOn),
            self::buildLocales($command->locales),
            self::buildFiles($command->files),
            self::buildClues($command->clues),
            null !== $command->description ? NonEmptyStringValue::create($command->description) : null,
            null !== $command->endsOn ? DateTime::create($command->endsOn) : null,
            $command->coverPath,
            self::buildMeetings($command->meetings),
        );

        $this->kalRepository->create($kal);
    }

    /**
     * @param list<mixed> $locales
     *
     * @throws KalException
     * @throws KalStateException
     * @throws InvalidArgumentException
     */
    private static function buildLocales(array $locales): Locales
    {
        return Locales::create(array_map(
            static fn (mixed $code): Locale => Locale::fromString(self::toString($code)),
            $locales,
        ));
    }

    /**
     * @param list<mixed> $files
     *
     * @throws InvalidArgumentException
     * @throws KalFileException
     */
    private static function buildFiles(array $files): Files
    {
        return Files::create(array_map(
            static fn (mixed $data): File => self::buildFile(self::toArray($data)),
            $files,
        ));
    }

    /**
     * @param list<mixed> $clues
     *
     * @throws KalException
     * @throws InvalidArgumentException
     * @throws KalFileException
     */
    private static function buildClues(array $clues): Clues
    {
        return Clues::create(array_map(
            static fn (mixed $data): Clue => self::buildClue(self::toArray($data)),
            $clues,
        ));
    }

    /**
     * @param list<mixed> $meetings
     *
     * @throws KalException
     * @throws KalStateException
     * @throws InvalidArgumentException
     */
    private static function buildMeetings(array $meetings): Meetings
    {
        return Meetings::create(array_map(
            static fn (mixed $data): Meeting => self::buildMeeting(self::toArray($data)),
            $meetings,
        ));
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws KalException
     * @throws InvalidArgumentException
     * @throws KalFileException
     */
    private static function buildClue(array $data): Clue
    {
        $description = self::nullableString($data, 'description');

        return Clue::create(
            NonEmptyStringValue::create(self::string($data, 'name')),
            DateTime::create(self::string($data, 'startsOn')),
            DateTime::create(self::string($data, 'endsOn')),
            self::buildFile(self::toArray($data['file'] ?? null)),
            self::buildMeeting(self::toArray($data['meeting'] ?? null)),
            Locale::fromString(self::string($data, 'locale')),
            null !== $description ? NonEmptyStringValue::create($description) : null,
        );
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidArgumentException
     * @throws KalFileException
     */
    private static function buildFile(array $data): File
    {
        return new File(
            NonEmptyStringValue::create(self::string($data, 'fileName')),
            NonEmptyStringValue::create(self::string($data, 'filePath')),
            FileSize::create(self::integer($data, 'fileSize')),
            FileExtension::tryFromStatus(self::string($data, 'fileExtension')),
            Locale::fromString(self::string($data, 'locale')),
            UlidValue::create(self::string($data, 'uploadId')),
            DateTime::create(self::string($data, 'uploadedAt')),
        );
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws KalException
     * @throws InvalidArgumentException
     */
    private static function buildMeeting(array $data): Meeting
    {
        $timezone = self::nullableString($data, 'timezone');

        return Meeting::create(
            DateTime::create(self::string($data, 'scheduledAt'), $timezone ?? Meeting::DEFAULT_TIMEZONE),
            HttpsUrl::fromString(self::string($data, 'url')),
            NonEmptyStringValue::create(self::string($data, 'title')),
            $timezone,
        );
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidArgumentException
     */
    private static function string(array $data, string $key): string
    {
        return self::toString($data[$key] ?? null);
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidArgumentException
     */
    private static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return null === $value ? null : self::toString($value);
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidArgumentException
     */
    private static function integer(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        if (!\is_int($value)) {
            throw InvalidArgumentException::invalidPayload();
        }

        return $value;
    }

    /** @throws InvalidArgumentException */
    private static function toString(mixed $value): string
    {
        if (!\is_string($value) || '' === $value) {
            throw InvalidArgumentException::invalidPayload();
        }

        return $value;
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws InvalidArgumentException
     */
    private static function toArray(mixed $value): array
    {
        if (!\is_array($value)) {
            throw InvalidArgumentException::invalidPayload();
        }

        return $value;
    }
}
