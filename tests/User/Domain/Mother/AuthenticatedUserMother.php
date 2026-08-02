<?php

declare(strict_types=1);

namespace Tests\User\Domain\Mother;

use App\User\Domain\AuthenticatedUser;
use App\User\Domain\ExternalId;
use App\User\Domain\UserId;

final class AuthenticatedUserMother
{
    public static function create(?UserId $id = null, ?ExternalId $externalId = null): AuthenticatedUser
    {
        return new AuthenticatedUser(
            $id ?? UserIdMother::random(),
            $externalId ?? ExternalIdMother::random(),
        );
    }
}
