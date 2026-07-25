<?php

declare(strict_types=1);

namespace Tests\Kal\Domain\Mother;

use App\Kal\Domain\File;
use App\Kal\Domain\Files;
use App\Shared\Domain\ValueObject\Locale;

final class FilesMother
{
    public static function empty(): Files
    {
        return Files::create();
    }

    public static function of(File ...$files): Files
    {
        return Files::create(...$files);
    }

    public static function withLocale(Locale $locale): Files
    {
        return Files::create(FileMother::withLocale($locale));
    }
}
