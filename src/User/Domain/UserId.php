<?php

declare(strict_types=1);

namespace App\User\Domain;

use App\Shared\Domain\ValueObject\UlidValue;

// L'id intern de `profiles.id`: el ULID que fa servir tot el domini
// (`kals.organizer_id` i la resta de FKs). Tipus propi, no un `UlidValue`
// qualsevol, perquè cap cridador pugui confondre'l amb l'uuid de Supabase.
final readonly class UserId extends UlidValue
{
}
