<?php

declare(strict_types=1);

namespace App\User\Domain;

use App\User\Domain\Exception\UserException;

interface UserRepositoryInterface
{
    // No trobar perfil és un resultat normal (null), no una excepció: qui
    // autentica el tradueix a `auth_profile_not_found`.
    /** @throws UserException */
    public function findByExternalId(ExternalId $externalId): ?AuthenticatedUser;
}
