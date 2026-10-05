<?php

declare(strict_types=1);

namespace App\Kal\Application\Command\Kal;

use App\Shared\Application\Command\CommandInterface;

final readonly class ReplaceKalFileCommand implements CommandInterface
{
    /**
     * @param array<array-key, mixed> $payload
     */
    public function __construct(
        public string $kalId,
        public string $organizerId,
        public array $payload,
    ) {
    }
}
