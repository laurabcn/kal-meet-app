<?php

declare(strict_types=1);

namespace App\Kal\Domain\Repository;

use App\Kal\Domain\Exception\KalAlreadyMemberException;
use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Participation;
use App\Shared\Domain\ValueObject\UlidValue;

interface ParticipationRepositoryInterface
{
    /**
     * `KalAlreadyMemberException` només cobreix la cursa entre el check del
     * handler i l'insert; el cas normal el decideix `exists()`.
     *
     * @throws KalAlreadyMemberException
     * @throws KalException
     */
    public function create(Participation $participation): void;

    /** @throws KalException */
    public function exists(UlidValue $kalId, UlidValue $userId): bool;
}
