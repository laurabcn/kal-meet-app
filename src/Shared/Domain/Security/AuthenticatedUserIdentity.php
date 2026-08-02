<?php

declare(strict_types=1);

namespace App\Shared\Domain\Security;

use App\Shared\Domain\Exception\InvalidArgumentException;
use Symfony\Component\Uid\Ulid;

/**
 * La identitat que travessa la frontera d'autenticació: el ULID intern de
 * `profiles.id` (el que fa servir el domini) lligat a l'uuid de Supabase Auth.
 * L'uuid mai és la identitat del domini.
 */
final readonly class AuthenticatedUserIdentity
{
    /**
     * @param non-empty-string $id         ULID de `profiles.id`
     * @param non-empty-string $externalId uuid de `auth.users`, a `profiles.external_id`
     */
    public function __construct(
        public string $id,
        public string $externalId,
    ) {
    }

    /** @throws InvalidArgumentException */
    public static function create(string $id, string $externalId): self
    {
        if ('' === $id || '' === $externalId || !Ulid::isValid($id)) {
            throw new InvalidArgumentException('auth_identity_incomplete');
        }

        return new self($id, $externalId);
    }
}
