<?php

declare(strict_types=1);

namespace App\User\Domain;

use App\Shared\Domain\ValueObject\NonEmptyStringValue;

// L'id del proveïdor d'autenticació (`profiles.external_id`), avui l'uuid
// d'`auth.users` de Supabase. Només s'exigeix que no sigui buit: validar-lo com
// a uuid tancaria la porta als tokens de servei del MCP, previstos al CLAUDE.md.
final readonly class ExternalId extends NonEmptyStringValue
{
}
