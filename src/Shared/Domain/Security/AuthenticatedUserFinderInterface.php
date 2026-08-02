<?php

declare(strict_types=1);

namespace App\Shared\Domain\Security;

/**
 * Port de lectura de la identitat: `profiles.external_id` → `profiles.id`.
 *
 * L'implementa el context `src/User/` (que es construeix en paral·lel): és
 * l'únic que sap com llegir `profiles`. Aquest port viu a Shared perquè qui el
 * consumeix és l'adaptador d'autenticació de `Shared/Infrastructure`, i Shared
 * no pot dependre d'un bounded context concret.
 */
interface AuthenticatedUserFinderInterface
{
    public function findByExternalId(string $externalId): ?AuthenticatedUserIdentity;
}
