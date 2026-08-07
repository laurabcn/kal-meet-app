<?php

declare(strict_types=1);

namespace App\Kal\Application\Command\Kal;

use App\Shared\Application\Command\CommandInterface;

final readonly class UpdateKalCommand implements CommandInterface
{
    /**
     * Partial update: only keys present in `$changes` are applied.
     * Nullable fields may be present with `null` to clear them.
     *
     * @param array{
     *     name?: string,
     *     description?: ?string,
     *     startsOn?: string,
     *     endsOn?: ?string,
     *     coverPath?: ?string
     * } $changes
     */
    public function __construct(
        public string $id,
        public string $organizerId,
        public array $changes,
    ) {
    }
}
