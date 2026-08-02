<?php

declare(strict_types=1);

namespace App\User\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class UserException extends DomainException
{
    public static function persistenceFailed(\Throwable $cause): self
    {
        return new self('user_persistence_failed', 0, $cause);
    }

    public static function invalidStoredProfileId(?\Throwable $cause = null): self
    {
        return new self('user_invalid_stored_profile_id', 0, $cause);
    }
}
