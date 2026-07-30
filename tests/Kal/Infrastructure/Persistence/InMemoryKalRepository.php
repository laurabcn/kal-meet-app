<?php

declare(strict_types=1);

namespace Tests\Kal\Infrastructure\Persistence;

use App\Kal\Domain\Kal;
use App\Kal\Domain\KalRepositoryInterface;

final class InMemoryKalRepository implements KalRepositoryInterface
{
    /** @var Kal[] */
    private array $kals = [];

    private int $debateRoomsCreated = 0;

    public function create(Kal $kal): void
    {
        $this->kals[$kal->id->value()] = $kal;
        ++$this->debateRoomsCreated;
    }

    public function findById(string $id): ?Kal
    {
        return $this->kals[$id] ?? null;
    }

    /** @return Kal[] */
    public function all(): array
    {
        return array_values($this->kals);
    }

    public function debateRoomsCreated(): int
    {
        return $this->debateRoomsCreated;
    }
}
