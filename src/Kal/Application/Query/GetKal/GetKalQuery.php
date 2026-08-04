<?php

declare(strict_types=1);

namespace App\Kal\Application\Query\GetKal;

use App\Shared\Application\Query\QueryInterface;

final readonly class GetKalQuery implements QueryInterface
{
    public function __construct(
        public string $id,
        public string $callerId,
    ) {
    }
}
