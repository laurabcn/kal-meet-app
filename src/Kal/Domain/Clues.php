<?php

declare(strict_types=1);

namespace App\Kal\Domain;

use App\Kal\Domain\Exception\ClueNotFoundException;
use App\Shared\Domain\ValueObject\UlidValue;

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

    /** @throws ClueNotFoundException */
    public function get(UlidValue $id): Clue
    {
        foreach ($this->clues as $clue) {
            if ($clue->id->equals($id)) {
                return $clue;
            }
        }

        throw ClueNotFoundException::create();
    }

    /** @throws ClueNotFoundException */
    public function replace(Clue $clue): void
    {
        foreach ($this->clues as $position => $current) {
            if ($current->id->equals($clue->id)) {
                $this->clues[$position] = $clue;

                return;
            }
        }

        throw ClueNotFoundException::create();
    }

    /** @throws ClueNotFoundException */
    public function remove(UlidValue $id): void
    {
        foreach ($this->clues as $position => $clue) {
            if ($clue->id->equals($id)) {
                unset($this->clues[$position]);
                // Reindexa: la col·lecció s'exposa com a llista i un forat als
                // índexs es filtraria fins al JSON com un objecte, no un array.
                $this->clues = array_values($this->clues);

                return;
            }
        }

        throw ClueNotFoundException::create();
    }

    public function count(): int
    {
        return \count($this->clues);
    }
}
