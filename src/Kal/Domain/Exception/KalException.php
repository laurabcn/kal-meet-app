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

    public static function clueLocaleNotEnabled(): self
    {
        return new self('kal_clue_locale_not_enabled');
    }

    public static function meetingOutsideClueRange(): self
    {
        return new self('kal_meeting_outside_clue_range');
    }

    public static function emptyInviteToken(): self
    {
        return new self('kal_empty_invite_token');
    }

    public static function invalidMeetingTimezone(): self
    {
        return new self('kal_meeting_invalid_timezone');
    }

    public static function inviteTokenGenerationFailed(): self
    {
        return new self('kal_invite_token_generation_failed');
    }

    public static function persistenceFailed(\Throwable $cause): self
    {
        return new self('kal_persistence_failed', 0, $cause);
    }
}
