<?php

declare(strict_types=1);

namespace App\User\Domain\Exception;

use App\Shared\Domain\Exception\CorruptedStateException;

/**
 * Errors del context User que NO són culpa de qui fa la petició: perfil que
 * no es pot carregar, o fila persistida que no passa els invariants.
 *
 * Extén `CorruptedStateException` perquè el status el decideix el TIPUS, no el
 * text del codi (CLAUDE.md). Sense això `user_persistence_failed` tornaria a
 * caure al 400 per defecte i `ApiExceptionSubscriber` no el logaria ni
 * alertaria — el mateix silenci que el PR de Kal va arreglar.
 */
final class UserException extends CorruptedStateException
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
