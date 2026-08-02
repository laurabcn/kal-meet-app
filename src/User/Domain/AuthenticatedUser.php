<?php

declare(strict_types=1);

namespace App\User\Domain;

final readonly class AuthenticatedUser
{
    public function __construct(
        public UserId $id,
        public ExternalId $externalId,
    ) {
    }
}
