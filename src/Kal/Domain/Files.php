<?php

declare(strict_types=1);

namespace App\Kal\Domain;

final readonly class Files
{
    /** @param  File[] $files*/
    private function __construct(private array $files)
    {
    }

    /** @param  File[] $files*/
    public static function create(array $files): self
    {
        return new self($files);
    }

    /** @return File[] */
    public function all(): array
    {
        return $this->files;
    }
}
