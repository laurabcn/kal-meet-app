<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Security;

use App\Shared\Domain\Security\AuthenticatedUserIdentity;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * La usuària autenticada tal com la veu Symfony. La identitat és sempre el
 * ULID intern de `profiles.id`; l'uuid de Supabase Auth queda com a dada
 * secundària (traçabilitat i logs), mai com a identificador del domini.
 */
final readonly class SupabaseUser implements UserInterface
{
    public function __construct(
        private AuthenticatedUserIdentity $identity,
    ) {
    }

    /** @return non-empty-string */
    public function id(): string
    {
        return $this->identity->id;
    }

    /** @return non-empty-string */
    public function externalId(): string
    {
        return $this->identity->externalId;
    }

    /** @return non-empty-string */
    public function getUserIdentifier(): string
    {
        return $this->identity->id;
    }

    /**
     * L'autorització real (organitzadora d'un KAL, membre d'un KAL) es deriva
     * del context i encara no existeix: veure Non-Goals del spec.
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }
}
