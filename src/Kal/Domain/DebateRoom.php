<?php

declare(strict_types=1);

namespace App\Kal\Domain;

use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\UlidValue;

/**
 * L'aula de xat del KAL, i la pantalla on aterra una participant quan hi entra.
 *
 * No té contingut propi: els missatges (`debate_messages`) viuen fora de
 * l'agregat i el frontend els llegeix i escriu directament amb `supabase-js`.
 * L'aula existeix perquè hi hagi on penjar-los i on subscriure's a Realtime.
 *
 * Sempre a nivell de KAL i mai per pista, i una sola per KAL a l'MVP — ho fa
 * complir l'índex únic `debate_rooms_kal_id_unique`. Fase 2 en preveu vàries,
 * típicament per idioma.
 */
final class DebateRoom
{
    private function __construct(
        public private(set) readonly UlidValue $id,
        public private(set) readonly DateTime $createdAt,
    ) {
    }

    /** @throws InvalidArgumentException */
    public static function create(): self
    {
        return new self(UlidValue::generate(), DateTime::now());
    }

    public static function reconstitute(UlidValue $id, DateTime $createdAt): self
    {
        return new self($id, $createdAt);
    }
}
