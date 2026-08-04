<?php

declare(strict_types=1);

namespace App\User\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class UserException extends DomainException
{
    public static function persistenceFailed(\Throwable $cause): self
    {
        return new self('user_persistence_failed', 'Failed to load the user profile.', 0, $cause);
    }

    public static function invalidStoredProfileId(?\Throwable $cause = null): self
    {
        return new self(
            'user_invalid_stored_profile_id',
            'The stored profile id is invalid.',
            0,
            $cause,
        );
    }
}
