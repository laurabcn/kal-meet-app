<?php

declare(strict_types=1);

namespace App\Kal\Domain;

final class Clues
{
    /** @param Clue[] $clues */
    private function __construct(private array $clues)
    {
    }

    /** @param Clue[] $clues */
    public static function create(array $clues): self
    {
        return new self($clues);
    }

    /**
     * @return Clue[]
     */
    public function all(): array
    {
        return $this->clues;
    }

    public function add(Clue $clue): void
    {
        $this->clues[] = $clue;
    }
}
