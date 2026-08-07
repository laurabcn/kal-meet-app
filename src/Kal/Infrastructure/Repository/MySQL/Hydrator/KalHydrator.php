<?php

declare(strict_types=1);

namespace App\Kal\Infrastructure\Repository\MySQL\Hydrator;

use App\Kal\Domain\Clue;
use App\Kal\Domain\Clues;
use App\Kal\Domain\DebateRoom;
use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Exception\KalFileException;
use App\Kal\Domain\Exception\KalStateException;
use App\Kal\Domain\File;
use App\Kal\Domain\FileExtension;
use App\Kal\Domain\Files;
use App\Kal\Domain\FileSize;
use App\Kal\Domain\InviteToken;
use App\Kal\Domain\Kal;
use App\Kal\Domain\Locales;
use App\Kal\Domain\Meeting;
use App\Kal\Domain\Meetings;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\Repository\Hydrator\HydratorInterface;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\HttpsUrl;
use App\Shared\Domain\ValueObject\Locale;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use App\Shared\Domain\ValueObject\UlidValue;

/** @template-implements HydratorInterface<Kal> */
final readonly class KalHydrator implements HydratorInterface
{
    /**
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException
     * @throws KalException
     * @throws KalStateException
     * @throws KalFileException
     * @throws \TypeError
     * @throws \ValueError
     */
    public function hydrate(array $data): Kal
    {
        $meetingRows = self::parseRowList($data['meetings'] ?? []);
        $kalMeetings = [];
        /** @var array<string, Meeting> $meetingsByClueId */
        $meetingsByClueId = [];

        foreach ($meetingRows as $row) {
            $meeting = $this->hydrateMeeting($row);
            $clueId = $this->optionalString($row, 'clue_id');
            if (null === $clueId) {
                $kalMeetings[] = $meeting;
                continue;
            }

            $meetingsByClueId[$clueId] = $meeting;
        }

        $clues = [];
        foreach (self::parseRowList($data['clues'] ?? []) as $row) {
            $clueId = $this->requiredString($row, 'id');
            $meeting = $meetingsByClueId[$clueId] ?? null;
            if (null === $meeting) {
                throw KalStateException::missingClueMeeting();
            }
            $clues[] = $this->hydrateClue($row, $meeting);
        }

        // Obligatòria i única (índex `debate_rooms_kal_id_unique`). Zero o
        // més d'una = agregat trencat; no triem una fila a l'atzar.
        $debateRoomRows = self::parseRowList($data['debate_rooms'] ?? []);
        if (1 !== \count($debateRoomRows)) {
            throw KalStateException::missingDebateRoom();
        }

        $debateRoomRow = $debateRoomRows[0];
        $debateRoom = DebateRoom::reconstitute(
            UlidValue::create($this->requiredString($debateRoomRow, 'id')),
            DateTime::create($this->requiredDateTime($debateRoomRow, 'created_at')),
        );

        return Kal::reconstitute(
            id: UlidValue::create($this->requiredString($data, 'id')),
            organizerId: UlidValue::create($this->requiredString($data, 'organizer_id')),
            name: NonEmptyStringValue::create($this->requiredString($data, 'name')),
            description: null !== ($description = $this->optionalString($data, 'description'))
                ? NonEmptyStringValue::create($description)
                : null,
            files: Files::create(array_map(
                $this->hydrateKalFile(...),
                self::parseRowList($data['files'] ?? []),
            )),
            clues: Clues::create($clues),
            locales: Locales::create(array_map(
                static fn (string $code): Locale => Locale::fromString($code),
                self::parseStringList($data['locales'] ?? []),
            )),
            startsOn: DateTime::create($this->requiredDateTime($data, 'starts_on')),
            endsOn: null !== ($endsOn = $this->optionalDateTime($data, 'ends_on'))
                ? DateTime::create($endsOn)
                : null,
            coverPath: $this->optionalString($data, 'cover_path'),
            inviteToken: InviteToken::create($this->requiredString($data, 'invite_token')),
            meetings: Meetings::create($kalMeetings),
            debateRoom: $debateRoom,
            createdAt: DateTime::create($this->requiredDateTime($data, 'created_at')),
            updatedAt: DateTime::create($this->requiredDateTime($data, 'updated_at')),
        );
    }

    /**
     * @return array{
     *     kal: array{
     *         id: string,
     *         organizer_id: string,
     *         name: string,
     *         description: ?string,
     *         starts_on: string,
     *         ends_on: ?string,
     *         cover_path: ?string,
     *         invite_token: string,
     *         created_at: string,
     *         updated_at: string
     *     },
     *     locales: list<array{kal_id: string, locale: string}>,
     *     files: list<array{
     *         upload_id: string,
     *         kal_id: string,
     *         file_name: string,
     *         file_path: string,
     *         file_size: int,
     *         file_extension: string,
     *         locale: string,
     *         uploaded_at: string
     *     }>,
     *     clues: list<array{
     *         id: string,
     *         kal_id: string,
     *         name: string,
     *         description: ?string,
     *         starts_on: string,
     *         ends_on: string,
     *         updated_at: string,
     *         locale: string,
     *         file_name: string,
     *         file_path: string,
     *         file_size: int,
     *         file_extension: string,
     *         file_locale: string,
     *         file_upload_id: string,
     *         file_uploaded_at: string
     *     }>,
     *     meetings: list<array{
     *         id: string,
     *         kal_id: string,
     *         clue_id: ?string,
     *         title: string,
     *         url: string,
     *         scheduled_at: string,
     *         timezone: string
     *     }>,
     *     debate_room: array{
     *         id: string,
     *         kal_id: string,
     *         created_at: string
     *     }
     * }
     *
     * @throws KalStateException
     */
    public function extract(object $object): array
    {
        if (!$object instanceof Kal) {
            throw KalStateException::invalidKal();
        }

        /** @var Kal $object */
        $kalId = $object->id->value();

        return [
            'kal' => [
                'id' => $kalId,
                'organizer_id' => $object->organizerId->value(),
                'name' => $object->name->value(),
                'description' => $object->description?->value(),
                'starts_on' => $object->startsOn->value(),
                'ends_on' => $object->endsOn?->value(),
                'cover_path' => $object->coverPath,
                'invite_token' => $object->inviteToken->value(),
                'created_at' => $object->createdAt->value(),
                'updated_at' => $object->updatedAt->value(),
            ],
            'locales' => array_values(array_map(
                static fn (Locale $locale): array => [
                    'kal_id' => $kalId,
                    'locale' => $locale->value(),
                ],
                $object->locales->all(),
            )),
            'files' => array_values(array_map(
                static fn (File $file): array => self::extractFile($kalId, $file),
                $object->files->all(),
            )),
            'clues' => array_values(array_map(
                static fn (Clue $clue): array => self::extractClue($kalId, $clue),
                $object->clues->all(),
            )),
            'meetings' => self::extractMeetings($kalId, $object),
            'debate_room' => [
                'id' => $object->debateRoom->id->value(),
                'kal_id' => $kalId,
                'created_at' => $object->debateRoom->createdAt->value(),
            ],
        ];
    }

    /**
     * @return array{
     *     upload_id: string,
     *     kal_id: string,
     *     file_name: string,
     *     file_path: string,
     *     file_size: int,
     *     file_extension: string,
     *     locale: string,
     *     uploaded_at: string
     * }
     */
    private static function extractFile(string $kalId, File $file): array
    {
        return [
            'upload_id' => $file->uploadId->value(),
            'kal_id' => $kalId,
            'file_name' => $file->fileName->value(),
            'file_path' => $file->filePath->value(),
            'file_size' => $file->fileSize->value(),
            'file_extension' => $file->fileExtension->value(),
            'locale' => $file->locale->value(),
            'uploaded_at' => $file->uploadedAt->value(),
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     kal_id: string,
     *     name: string,
     *     description: ?string,
     *     starts_on: string,
     *     ends_on: string,
     *     updated_at: string,
     *     locale: string,
     *     file_name: string,
     *     file_path: string,
     *     file_size: int,
     *     file_extension: string,
     *     file_locale: string,
     *     file_upload_id: string,
     *     file_uploaded_at: string
     * }
     */
    private static function extractClue(string $kalId, Clue $clue): array
    {
        $file = $clue->file;

        return [
            'id' => $clue->id->value(),
            'kal_id' => $kalId,
            'name' => $clue->name->value(),
            'description' => $clue->description?->value(),
            'starts_on' => $clue->startsOn->value(),
            'ends_on' => $clue->endsOn->value(),
            'updated_at' => $clue->updatedAt->value(),
            'locale' => $clue->locale->value(),
            'file_name' => $file->fileName->value(),
            'file_path' => $file->filePath->value(),
            'file_size' => $file->fileSize->value(),
            'file_extension' => $file->fileExtension->value(),
            'file_locale' => $file->locale->value(),
            'file_upload_id' => $file->uploadId->value(),
            'file_uploaded_at' => $file->uploadedAt->value(),
        ];
    }

    /**
     * @return list<array{
     *     id: string,
     *     kal_id: string,
     *     clue_id: ?string,
     *     title: string,
     *     url: string,
     *     scheduled_at: string,
     *     timezone: string
     * }>
     */
    private static function extractMeetings(string $kalId, Kal $kal): array
    {
        $meetings = array_map(
            static fn (Meeting $meeting): array => self::extractMeeting($kalId, $meeting),
            $kal->meetings->all(),
        );

        foreach ($kal->clues->all() as $clue) {
            $meetings[] = self::extractMeeting($kalId, $clue->meeting, $clue->id->value());
        }

        return array_values($meetings);
    }

    /**
     * @return array{
     *     id: string,
     *     kal_id: string,
     *     clue_id: ?string,
     *     title: string,
     *     url: string,
     *     scheduled_at: string,
     *     timezone: string
     * }
     */
    private static function extractMeeting(string $kalId, Meeting $meeting, ?string $clueId = null): array
    {
        return [
            'id' => $meeting->id->value(),
            'kal_id' => $kalId,
            'clue_id' => $clueId,
            'title' => $meeting->title->value(),
            'url' => $meeting->url->value(),
            'scheduled_at' => $meeting->scheduledAt->value(),
            'timezone' => $meeting->timezone,
        ];
    }

    /** @throws InvalidArgumentException */
    private function parseString(mixed $value): string
    {
        if (!is_string($value)) {
            throw InvalidArgumentException::notAString();
        }

        return $value;
    }

    /** @throws InvalidArgumentException */
    private function parseDateTime(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DateTime::FORMAT);
        }

        return $this->parseString($value);
    }

    /** @throws InvalidArgumentException */
    private function parseInteger(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        throw InvalidArgumentException::notAnInteger();
    }

    /**
     * @return list<string>
     *
     * @throws InvalidArgumentException
     */
    private static function parseStringList(mixed $value): array
    {
        if (!is_array($value)) {
            throw InvalidArgumentException::notAString();
        }

        $items = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                throw InvalidArgumentException::notAString();
            }
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws InvalidArgumentException
     */
    private static function parseRowList(mixed $value): array
    {
        if (!\is_array($value)) {
            throw InvalidArgumentException::notAString();
        }

        $rows = [];
        foreach ($value as $item) {
            if (!\is_array($item)) {
                throw InvalidArgumentException::notAString();
            }

            $row = [];
            foreach ($item as $key => $cell) {
                if (!\is_string($key)) {
                    throw InvalidArgumentException::notAString();
                }
                $row[$key] = $cell;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @throws InvalidArgumentException
     * @throws KalFileException
     */
    private function hydrateKalFile(array $row): File
    {
        return new File(
            NonEmptyStringValue::create($this->requiredString($row, 'file_name')),
            NonEmptyStringValue::create($this->requiredString($row, 'file_path')),
            FileSize::create($this->requiredInteger($row, 'file_size')),
            FileExtension::tryFromStatus($this->requiredString($row, 'file_extension')),
            Locale::fromString($this->requiredString($row, 'locale')),
            UlidValue::create($this->requiredString($row, 'upload_id')),
            DateTime::create($this->requiredDateTime($row, 'uploaded_at')),
        );
    }

    /**
     * @param array<string, mixed> $row
     *
     * @throws InvalidArgumentException
     * @throws KalFileException
     * @throws KalException
     */
    private function hydrateClue(array $row, Meeting $meeting): Clue
    {
        $file = new File(
            NonEmptyStringValue::create($this->requiredString($row, 'file_name')),
            NonEmptyStringValue::create($this->requiredString($row, 'file_path')),
            FileSize::create($this->requiredInteger($row, 'file_size')),
            FileExtension::tryFromStatus($this->requiredString($row, 'file_extension')),
            Locale::fromString($this->requiredString($row, 'file_locale')),
            UlidValue::create($this->requiredString($row, 'file_upload_id')),
            DateTime::create($this->requiredDateTime($row, 'file_uploaded_at')),
        );

        return Clue::reconstitute(
            id: UlidValue::create($this->requiredString($row, 'id')),
            name: NonEmptyStringValue::create($this->requiredString($row, 'name')),
            description: null !== ($description = $this->optionalString($row, 'description'))
                ? NonEmptyStringValue::create($description)
                : null,
            file: $file,
            meeting: $meeting,
            locale: Locale::fromString($this->requiredString($row, 'locale')),
            startsOn: DateTime::create($this->requiredDateTime($row, 'starts_on')),
            endsOn: DateTime::create($this->requiredDateTime($row, 'ends_on')),
            updatedAt: DateTime::create($this->requiredDateTime($row, 'updated_at')),
        );
    }

    /**
     * @param array<string, mixed> $row
     *
     * @throws InvalidArgumentException
     * @throws KalException
     */
    private function hydrateMeeting(array $row): Meeting
    {
        return Meeting::reconstitute(
            id: UlidValue::create($this->requiredString($row, 'id')),
            scheduledAt: DateTime::create($this->requiredDateTime($row, 'scheduled_at')),
            url: HttpsUrl::fromString($this->requiredString($row, 'url')),
            title: NonEmptyStringValue::create($this->requiredString($row, 'title')),
            timezone: $this->requiredString($row, 'timezone'),
        );
    }

    /**
     * @param array<string, mixed> $row
     *
     * @throws InvalidArgumentException
     */
    private function requiredString(array $row, string $key): string
    {
        if (!\array_key_exists($key, $row) || null === $row[$key]) {
            throw InvalidArgumentException::notAString();
        }

        return $this->parseString($row[$key]);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @throws InvalidArgumentException
     */
    private function optionalString(array $row, string $key): ?string
    {
        if (!\array_key_exists($key, $row) || null === $row[$key] || '' === $row[$key]) {
            return null;
        }

        return $this->parseString($row[$key]);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @throws InvalidArgumentException
     */
    private function requiredDateTime(array $row, string $key): string
    {
        if (!\array_key_exists($key, $row) || null === $row[$key] || '' === $row[$key]) {
            throw InvalidArgumentException::notAString();
        }

        return $this->parseDateTime($row[$key]);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @throws InvalidArgumentException
     */
    private function optionalDateTime(array $row, string $key): ?string
    {
        if (!\array_key_exists($key, $row) || null === $row[$key] || '' === $row[$key]) {
            return null;
        }

        return $this->parseDateTime($row[$key]);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @throws InvalidArgumentException
     */
    private function requiredInteger(array $row, string $key): int
    {
        if (!\array_key_exists($key, $row) || null === $row[$key]) {
            throw InvalidArgumentException::notAnInteger();
        }

        return $this->parseInteger($row[$key]);
    }
}
