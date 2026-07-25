<?php

declare(strict_types=1);

namespace Tests\Kal\Domain\Mother;

use App\Kal\Domain\Locales;
use App\Shared\Domain\ValueObject\Locale;
use Tests\Shared\Domain\ValueObject\Mother\LocaleMother;

final class LocalesMother
{
    public static function catalanAndSpanish(): Locales
    {
        return Locales::create(LocaleMother::catalan(), LocaleMother::spanish());
    }

    public static function catalanOnly(): Locales
    {
        return Locales::create(LocaleMother::catalan());
    }

    public static function of(Locale ...$locales): Locales
    {
        return Locales::create(...$locales);
    }
}
