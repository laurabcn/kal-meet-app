<?php

declare(strict_types=1);

namespace App\Kal\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class KalException extends DomainException
{
    public static function invalidDateRange(): self
    {
        return new self('kal_invalid_date_range', 'The kal end date must be after the start date.');
    }

    public static function clueOutsideKalRange(): self
    {
        return new self('kal_clue_outside_range', 'A clue date range falls outside the kal date range.');
    }

    public static function noLocalesEnabled(): self
    {
        return new self('kal_no_locales_enabled', 'At least one locale must be enabled for the kal.');
    }

    public static function fileLocaleNotEnabled(): self
    {
        return new self('kal_file_locale_not_enabled', 'A file locale is not enabled for this kal.');
    }

    public static function clueLocaleNotEnabled(): self
    {
        return new self('kal_clue_locale_not_enabled', 'A clue locale is not enabled for this kal.');
    }

    public static function meetingOutsideClueRange(): self
    {
        return new self('kal_meeting_outside_clue_range', 'A meeting is scheduled outside its clue date range.');
    }

    public static function emptyInviteToken(): self
    {
        return new self('kal_empty_invite_token', 'The invite token cannot be empty.');
    }

    public static function invalidMeetingTimezone(): self
    {
        return new self('kal_meeting_invalid_timezone', 'The meeting timezone is invalid.');
    }

    public static function inviteTokenGenerationFailed(): self
    {
        return new self('kal_invite_token_generation_failed', 'Failed to generate an invite token.');
    }

    public static function persistenceFailed(\Throwable $cause): self
    {
        return new self('kal_persistence_failed', 'Failed to persist the kal.', 0, $cause);
    }

    /** L'aula és on aterra la participant: un KAL sense ella no es pot servir. */
    public static function missingDebateRoom(): self
    {
        return new self('kal_debate_room_missing', 'The kal is missing its debate room.');
    }

    /** A persisted clue with no meeting row would break the "meeting obligatòria" invariant. */
    public static function missingClueMeeting(): self
    {
        return new self('kal_clue_meeting_missing', 'A clue is missing its required meeting.');
    }

    public static function invalidKal(): self
    {
        return new self('kal_invalid', 'The value is not a valid kal.');
    }
}
