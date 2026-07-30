<?php

declare(strict_types=1);

namespace App\Kal\Domain;

use App\Kal\Domain\Exception\KalException;
use App\Shared\Domain\ValueObject\Locale;

final readonly class Locales
{
    /** @param Locale[] $locales */
    public function __construct(
        private array $locales,
    ) {
    }

    /**
     * @param Locale[] $locales
     *
     * @throws KalException
     */
    public static function create(array $locales): self
    {
        self::guardNotEmpty($locales);

        $unique = [];
        foreach ($locales as $locale) {
            $unique[$locale->value()] ??= $locale;
        }

        return new self(array_values($unique));
    }

    public function contains(Locale $locale): bool
    {
        return array_any($this->locales, fn ($enabled) => $enabled->equals($locale));
    }

    /** @return Locale[] */
    public function all(): array
    {
        return $this->locales;
    }

    /**
     * @param Locale[] $locales
     *
     * @throws KalException
     */
    private static function guardNotEmpty(array $locales): void
    {
        if ([] === $locales) {
            throw KalException::noLocalesEnabled();
        }
    }
}
