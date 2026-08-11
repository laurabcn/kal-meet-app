<?php

declare(strict_types=1);

namespace App\Kal\Infrastructure\Repository\MySQL\Hydrator;

use App\Kal\Domain\KalSummary;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use App\Shared\Domain\ValueObject\UlidValue;

/**
 * No implementa `HydratorInterface` a propòsit: aquell contracte demana també
 * `extract()`, i un `KalSummary` no s'escriu mai enlloc. Tenir-hi un mètode
 * que llenci seria dir una mentida amb tipus.
 */
final readonly class KalSummaryHydrator
{
    /**
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException
     */
    public function hydrate(array $data): KalSummary
    {
        $description = $this->optionalString($data, 'description');
        $endsOn = $this->optionalDateTime($data, 'ends_on');

        return new KalSummary(
            UlidValue::create($this->requiredString($data, 'id')),
            NonEmptyStringValue::create($this->requiredString($data, 'name')),
            null !== $description ? NonEmptyStringValue::create($description) : null,
            DateTime::create($this->requiredDateTime($data, 'starts_on')),
            null !== $endsOn ? DateTime::create($endsOn) : null,
            $this->optionalString($data, 'cover_path'),
        );
    }

    /**
     * @param array<string, mixed> $row
     *
     * @throws InvalidArgumentException
     */
    private function requiredString(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        if (!\is_string($value)) {
            throw InvalidArgumentException::notAString();
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @throws InvalidArgumentException
     */
    private function optionalString(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw InvalidArgumentException::notAString();
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @throws InvalidArgumentException
     */
    private function requiredDateTime(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DateTime::FORMAT);
        }

        return $this->requiredString($row, $key);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @throws InvalidArgumentException
     */
    private function optionalDateTime(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        if (null === $value) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DateTime::FORMAT);
        }

        return $this->optionalString($row, $key);
    }
}
