<?php

declare(strict_types=1);

namespace App\Kal\Application\Command\Clue;

use App\Shared\Application\Command\CommandInterface;

final readonly class UpdateClueCommand implements CommandInterface
{
    /**
     * Partial update: only keys present in `$changes` are applied.
     * `description` may be present with `null` to clear it.
     *
     * @param array{
     *     name?: string,
     *     description?: ?string,
     *     startsOn?: string,
     *     endsOn?: string,
     *     locale?: string
     * } $changes
     */
    public function __construct(
        public string $kalId,
        public string $organizerId,
        public string $clueId,
        public array $changes,
    ) {
    }
}
