<?php

declare(strict_types=1);

namespace App\Kal\Domain;

final readonly class Files
{
    /** @var File[] */
    private array $files;

    private function __construct(File ...$files)
    {
        $this->files = $files;
    }

    public static function create(File ...$files): self
    {
        return new self(...$files);
    }

    /**
     * @return File[]
     */
    public function all(): array
    {
        return $this->files;
    }
}
