<?php

declare(strict_types=1);

namespace App\Kal\Domain;

use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use App\Shared\Domain\ValueObject\UlidValue;

/**
 * Vista de llista d'un KAL: prou per pintar una targeta, sense reconstruir
 * l'agregat. Un `Kal` sencer demana locales, files, clues, meetings i l'aula
 * — cinc consultes per fila que la llista no fa servir. No té invariants
 * perquè no és una arrel: no s'hi escriu mai.
 */
final readonly class KalSummary
{
    public function __construct(
        public UlidValue $id,
        public NonEmptyStringValue $name,
        public ?NonEmptyStringValue $description,
        public DateTime $startsOn,
        public ?DateTime $endsOn,
        public ?string $coverPath,
    ) {
    }
}
