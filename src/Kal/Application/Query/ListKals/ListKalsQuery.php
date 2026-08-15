<?php

declare(strict_types=1);

namespace App\Kal\Application\Query\ListKals;

use App\Shared\Application\Query\QueryInterface;

final readonly class ListKalsQuery implements QueryInterface
{
    public function __construct(
        public string $organizerId,
    ) {
    }
}
