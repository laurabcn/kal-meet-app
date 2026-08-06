<?php

declare(strict_types=1);

namespace App\Kal\Infrastructure\Repository\MySQL\Hydrator;

use App\Kal\Domain\Clue;
use App\Kal\Domain\Clues;
use App\Kal\Domain\DebateRoom;
use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Exception\KalFileException;
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
            $clueId = $row['clue_id'] ?? null;
            if (null === $clueId || '' === $clueId) {
                $kalMeetings[] = $meeting;
                continue;
            }

            $meetingsByClueId[$this->parseString($clueId)] = $meeting;
        }

        $clues = [];
        foreach (self::parseRowList($data['clues'] ?? []) as $row) {
            $clueId = $this->parseString($row['id'] ?? null);
            $meeting = $meetingsByClueId[$clueId] ?? null;
            if (null === $meeting) {
                throw KalException::missingClueMeeting();
            }
            $clues[] = $this->hydrateClue($row, $meeting);
        }

        // Obligatòria: un KAL sense aula és un KAL trencat, perquè és on aterra
        // la participant. La migració de backfill garanteix que no n'hi hagi cap
        // sense, i l'índex únic que no n'hi hagi dues.
        $debateRoomRow = self::parseRowList($data['debate_rooms'] ?? [])[0] ?? null;
        if (null === $debateRoomRow) {
            throw KalException::missingDebateRoom();
        }

        return Kal::reconstitute(
            id: UlidValue::create($this->parseString($data['id'])),
            organizerId: UlidValue::create($this->parseString($data['organizer_id'])),
            name: NonEmptyStringValue::create($this->parseString($data['name'])),
            description: $data['description'] ?
                NonEmptyStringValue::create($this->parseString($data['description'])) : null,
            files: Files::create(array_map(
                $this->hydrateKalFile(...),
                self::parseRowList($data['files'] ?? []),
            )),
            clues: Clues::create($clues),
            locales: Locales::create(array_map(
                static fn (string $code): Locale => Locale::fromString($code),
                self::parseStringList($data['locales'] ?? []),
            )),
            startsOn: DateTime::create($this->parseDateTime($data['starts_on'])),
            endsOn: $data['ends_on'] ? DateTime::create($this->parseDateTime($data['ends_on'])) : null,
            coverPath: $data['cover_path'] ? $this->parseString($data['cover_path']) : null,
            inviteToken: InviteToken::create($this->parseString($data['invite_token'])),
            meetings: Meetings::create($kalMeetings),
            debateRoom: DebateRoom::reconstitute(
                UlidValue::create($this->parseString($debateRoomRow['id'] ?? null)),
                DateTime::create($this->parseDateTime($debateRoomRow['created_at'] ?? null)),
            ),
            createdAt: DateTime::create($this->parseDateTime($data['created_at'])),
            updatedAt: DateTime::create($this->parseDateTime($data['updated_at'])),
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
     * @throws KalException
     */
    public function extract(object $object): array
    {
        if (!$object instanceof Kal) {
            throw KalException::invalidKal();
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
            NonEmptyStringValue::create($this->parseString($row['file_name'] ?? null)),
            NonEmptyStringValue::create($this->parseString($row['file_path'] ?? null)),
            FileSize::create($this->parseInteger($row['file_size'] ?? null)),
            FileExtension::tryFromStatus($this->parseString($row['file_extension'] ?? null)),
            Locale::fromString($this->parseString($row['locale'] ?? null)),
            UlidValue::create($this->parseString($row['upload_id'] ?? null)),
            DateTime::create($this->parseDateTime($row['uploaded_at'] ?? null)),
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
            NonEmptyStringValue::create($this->parseString($row['file_name'] ?? null)),
            NonEmptyStringValue::create($this->parseString($row['file_path'] ?? null)),
            FileSize::create($this->parseInteger($row['file_size'] ?? null)),
            FileExtension::tryFromStatus($this->parseString($row['file_extension'] ?? null)),
            Locale::fromString($this->parseString($row['file_locale'] ?? null)),
            UlidValue::create($this->parseString($row['file_upload_id'] ?? null)),
            DateTime::create($this->parseDateTime($row['file_uploaded_at'] ?? null)),
        );

        return Clue::reconstitute(
            id: UlidValue::create($this->parseString($row['id'] ?? null)),
            name: NonEmptyStringValue::create($this->parseString($row['name'] ?? null)),
            description: ($row['description'] ?? null) ?
                NonEmptyStringValue::create($this->parseString($row['description'])) : null,
            file: $file,
            meeting: $meeting,
            locale: Locale::fromString($this->parseString($row['locale'] ?? null)),
            startsOn: DateTime::create($this->parseDateTime($row['starts_on'] ?? null)),
            endsOn: DateTime::create($this->parseDateTime($row['ends_on'] ?? null)),
            updatedAt: DateTime::create($this->parseDateTime($row['updated_at'] ?? null)),
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
            id: UlidValue::create($this->parseString($row['id'] ?? null)),
            scheduledAt: DateTime::create($this->parseDateTime($row['scheduled_at'] ?? null)),
            url: HttpsUrl::fromString($this->parseString($row['url'] ?? null)),
            title: NonEmptyStringValue::create($this->parseString($row['title'] ?? null)),
            timezone: $this->parseString($row['timezone'] ?? null),
        );
    }
}
