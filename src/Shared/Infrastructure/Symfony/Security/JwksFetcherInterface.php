<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Security;

use App\Shared\Infrastructure\Symfony\Security\Exception\JwksFetchFailedException;

interface JwksFetcherInterface
{
    /**
     * @return array<array-key, mixed> el document JWKS tal com arriba, sense interpretar
     *
     * @throws JwksFetchFailedException
     */
    public function fetch(): array;
}
