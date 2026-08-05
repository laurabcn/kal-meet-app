<?php

declare(strict_types=1);

namespace App\Kal\Domain\Repository;

use App\Kal\Domain\Exception\KalAlreadyExistsException;
use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Exception\KalNotFoundException;
use App\Kal\Domain\InviteToken;
use App\Kal\Domain\Kal;
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

    /**
     * Active (non-soft-deleted) kal by id — no organizer filter (join path).
     *
     * @throws KalNotFoundException
     * @throws KalException
     */
    public function getActiveById(UlidValue $id): Kal;

    /**
     * Qui pot apuntar-s'hi no ho decideix el repositori: veure `JoinPolicy`.
     *
     * @throws KalNotFoundException
     * @throws KalException
     */
    public function findByToken(UlidValue $kalId, InviteToken $inviteToken): Kal;
}
