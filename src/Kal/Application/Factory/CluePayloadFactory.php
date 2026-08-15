<?php

declare(strict_types=1);

namespace App\Kal\Application\Factory;

use App\Kal\Domain\Clue;
use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Exception\KalFileException;
use App\Kal\Domain\File;
use App\Kal\Domain\FileExtension;
use App\Kal\Domain\FileSize;
use App\Kal\Domain\Meeting;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\HttpsUrl;
use App\Shared\Domain\ValueObject\Locale;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use App\Shared\Domain\ValueObject\UlidValue;

/**
 * Payload d'una pista → domini. Viu a part perquè hi ha dos camins d'entrada
 * amb la MATEIXA forma: les pistes incrustades al `POST /kal` i el
 * `POST /kal/{kalId}/clue`. Dues còpies d'aquest mapatge se separarien.
 */
final readonly class CluePayloadFactory
{
    /**
     * @param array<array-key, mixed> $data
     *
     * @throws KalException
     * @throws KalFileException
     * @throws InvalidArgumentException
     */
    public static function clue(UlidValue $id, array $data): Clue
    {
        $description = self::nullableString($data, 'description');

        return Clue::create(
            $id,
            NonEmptyStringValue::create(self::string($data, 'name')),
            DateTime::create(self::string($data, 'startsOn')),
            DateTime::create(self::string($data, 'endsOn')),
            self::file(self::toArray($data['file'] ?? null)),
            self::meeting(self::toArray($data['meeting'] ?? null)),
            Locale::fromString(self::string($data, 'locale')),
            null !== $description ? NonEmptyStringValue::create($description) : null,
        );
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws KalFileException
     * @throws InvalidArgumentException
     */
    public static function file(array $data): File
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
    public static function meeting(array $data): Meeting
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
    public static function string(array $data, string $key): string
    {
        return self::toString($data[$key] ?? null);
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidArgumentException
     */
    public static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return null === $value ? null : self::toString($value);
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidArgumentException
     */
    public static function integer(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        if (!\is_int($value)) {
            throw InvalidArgumentException::invalidPayload();
        }

        return $value;
    }

    /** @throws InvalidArgumentException */
    public static function toString(mixed $value): string
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
    public static function toArray(mixed $value): array
    {
        if (!\is_array($value)) {
            throw InvalidArgumentException::invalidPayload();
        }

        return $value;
    }
}
