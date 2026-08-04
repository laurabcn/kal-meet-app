<?php

declare(strict_types=1);

namespace App\Kal\Application\Command\Kal;

use App\Shared\Application\Command\CommandInterface;

final readonly class CreateKalCommand implements CommandInterface
{
    /**
     * @param list<mixed> $locales
     * @param list<mixed> $files
     * @param list<mixed> $clues
     * @param list<mixed> $meetings
     */
    public function __construct(
        public string $id,
        public string $organizerId,
        public string $name,
        public string $startsOn,
        public array $locales,
        public array $files = [],
        public array $clues = [],
        public ?string $description = null,
        public ?string $endsOn = null,
        public ?string $coverPath = null,
        public array $meetings = [],
    ) {
    }
}
