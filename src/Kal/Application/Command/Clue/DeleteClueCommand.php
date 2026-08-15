<?php

declare(strict_types=1);

namespace App\Kal\Application\Command\Clue;

use App\Shared\Application\Command\CommandInterface;

final readonly class DeleteClueCommand implements CommandInterface
{
    public function __construct(
        public string $kalId,
        public string $organizerId,
        public string $clueId,
    ) {
    }
}
