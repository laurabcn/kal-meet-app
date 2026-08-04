<?php

declare(strict_types=1);

namespace App\Kal\Domain\Repository;

use App\Kal\Domain\Exception\KalAlreadyMemberException;
use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Participation;

interface ParticipationRepositoryInterface
{
    /**
     * @throws KalAlreadyMemberException
     * @throws KalException
     */
    public function create(Participation $participation): void;
}
