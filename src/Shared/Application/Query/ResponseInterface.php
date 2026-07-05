<?php

declare(strict_types=1);

namespace App\Shared\Application\Query;

interface ResponseInterface
{
    /**
     * @return array<array-key, mixed>
     */
    public function result(): array;
}
