<?php

declare(strict_types=1);

namespace App\Kal\Domain;

final class Clues
{
    /** @var Clue[] */
    private array $clues;

    private function __construct(Clue ...$clues)
    {
        $this->clues = $clues;
    }

    public static function create(Clue ...$clues): self
    {
        return new self(...$clues);
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
