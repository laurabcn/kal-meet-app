<?php

declare(strict_types=1);

namespace Tests\Shared\Domain\ValueObject\Mother;

use App\Shared\Domain\ValueObject\Locale;

final class LocaleMother
{
    public static function catalan(): Locale
    {
        return Locale::fromString('ca');
    }

    public static function spanish(): Locale
    {
        return Locale::fromString('es');
    }

    public static function english(): Locale
    {
        return Locale::fromString('en');
    }

    public static function french(): Locale
    {
        return Locale::fromString('fr');
    }

    public static function fromString(string $code): Locale
    {
        return Locale::fromString($code);
    }
}
