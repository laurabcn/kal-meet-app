<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Security;

use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\Security\AuthenticatedUserFinderInterface;
use App\Shared\Domain\Security\AuthenticatedUserIdentity;
use App\User\Domain\Exception\UserException;
use App\User\Domain\ExternalId;
use App\User\Domain\UserRepositoryInterface;

/**
 * L'única implementació del port que Shared exposa perquè l'autenticació no
 * hagi de conèixer el context User. Tradueix als dos costats: string cru →
 * `ExternalId`, i `AuthenticatedUser` → `AuthenticatedUserIdentity`.
 */
final readonly class AuthenticatedUserFinder implements AuthenticatedUserFinderInterface
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    /**
     * Una fallada de BD NO es converteix en "no trobat": propaga i acaba en
     * 500. Si es tanqués aquí, una caiguda de Postgres es veuria des del
     * frontend com un `auth_profile_not_found`, que és una mentida cara de
     * depurar.
     *
     * @throws UserException
     * @throws InvalidArgumentException
     */
    public function findByExternalId(string $externalId): ?AuthenticatedUserIdentity
    {
        try {
            $user = $this->users->findByExternalId(new ExternalId($externalId));
        } catch (InvalidArgumentException) {
            // Un external_id buit no pot tenir perfil: cap consulta a fer.
            return null;
        }

        if (null === $user) {
            return null;
        }

        // Si el perfil persistit no és un ULID vàlid, que peti (500): no és
        // "perfil no trobat". UserId ja ho garanteix; si es trenqués, no
        // emmascarar corrupció com a auth_profile_not_found.
        return AuthenticatedUserIdentity::create($user->id->value(), $user->externalId->value());
    }
}
