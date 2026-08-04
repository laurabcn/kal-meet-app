<?php

declare(strict_types=1);

namespace App\Kal\Domain;

use App\Kal\Domain\Exception\KalAlreadyExistsException;
use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Exception\KalNotFoundException;
use App\Shared\Domain\ValueObject\UlidValue;

interface KalRepositoryInterface
{
    /**
     * @throws KalAlreadyExistsException
     * @throws KalException
     */
    public function create(Kal $kal): void;

    /**
     * @throws KalNotFoundException
     * @throws KalException
     */
    public function findById(UlidValue $id, UlidValue $organizerId): Kal;
}
