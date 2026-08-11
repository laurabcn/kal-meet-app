<?php

declare(strict_types=1);

namespace App\Kal\Application\Command\Kal;

use App\Shared\Application\Command\CommandInterface;

final readonly class DeleteKalCommand implements CommandInterface
{
    public function __construct(
        public string $id,
        public string $organizerId,
    ) {
    }
}
