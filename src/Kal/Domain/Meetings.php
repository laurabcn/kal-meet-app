<?php

declare(strict_types=1);

namespace App\Kal\Domain;

final class Meetings
{
    /** @param Meeting[] $meetings */
    private function __construct(private array $meetings)
    {
    }

    /** @param Meeting[] $meetings */
    public static function create(array $meetings): self
    {
        return new self($meetings);
    }

    /** @return Meeting[] */
    public function all(): array
    {
        return $this->meetings;
    }

    public function add(Meeting $meeting): void
    {
        $this->meetings[] = $meeting;
    }
}
