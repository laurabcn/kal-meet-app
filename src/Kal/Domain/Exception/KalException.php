<?php

declare(strict_types=1);

namespace App\Kal\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class KalException extends DomainException
{
    public static function invalidDateRange(): self
    {
        return new self('kal_invalid_date_range');
    }

    public static function clueOutsideKalRange(): self
    {
        return new self('kal_clue_outside_range');
    }

    public static function noLocalesEnabled(): self
    {
        return new self('kal_no_locales_enabled');
    }

    public static function fileLocaleNotEnabled(): self
    {
        return new self('kal_file_locale_not_enabled');
    }
}
