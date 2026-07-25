<?php

declare(strict_types=1);

namespace App\Kal\Domain;

use App\Kal\Domain\Exception\KalException;
use App\Shared\Domain\ValueObject\Locale;

final readonly class Locales
{
    /** @var Locale[] */
    private array $locales;

    /**
     * @throws KalException
     */
    private function __construct(Locale ...$locales)
    {
        $this->guardNotEmpty($locales);

        $this->locales = $locales;
    }

    /**
     * @throws KalException
     */
    public static function create(Locale ...$locales): self
    {
        return new self(...$locales);
    }

    public function contains(Locale $locale): bool
    {
        foreach ($this->locales as $enabled) {
            if ($enabled->equals($locale)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Locale[]
     */
    public function all(): array
    {
        return $this->locales;
    }

    /**
     * @param Locale[] $locales
     *
     * @throws KalException
     */
    private function guardNotEmpty(array $locales): void
    {
        if ([] === $locales) {
            throw KalException::noLocalesEnabled();
        }
    }
}
