<?php

declare(strict_types=1);

namespace App\Kal\Domain;

use App\Kal\Domain\Exception\KalException;

interface KalRepositoryInterface
{
    /** @throws KalException */
    public function create(Kal $kal): void;
}
