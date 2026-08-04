<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Security\Exception;

/**
 * Token vàlid però el `sub` no té fila a `profiles`: o el trigger
 * `handle_new_user()` ha fallat, o el perfil s'ha esborrat (RGPD).
 */
final class ProfileNotFoundException extends AuthenticationFailedException
{
    public function errorCode(): string
    {
        return 'auth_profile_not_found';
    }

    public function errorMessage(): string
    {
        return 'User profile was not found.';
    }
}
