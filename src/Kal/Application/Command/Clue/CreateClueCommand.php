<?php

declare(strict_types=1);

namespace App\Kal\Application\Command\Clue;

use App\Shared\Application\Command\CommandInterface;

final readonly class CreateClueCommand implements CommandInterface
{
    /**
     * `$clueId` ve de fora perquè el `POST` l'ha de tornar al client i el bus
     * de comandes no retorna res.
     *
     * @param array<array-key, mixed> $payload
     */
    public function __construct(
        public string $kalId,
        public string $organizerId,
        public string $clueId,
        public array $payload,
    ) {
    }
}
