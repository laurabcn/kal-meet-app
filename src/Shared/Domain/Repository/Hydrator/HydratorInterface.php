<?php

declare(strict_types=1);

namespace App\Shared\Domain\Repository\Hydrator;

/** @template TObject of object */
interface HydratorInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function hydrate(array $data): object;

    /**
     * @return array<string, mixed>
     */
    public function extract(object $object): array;
}
