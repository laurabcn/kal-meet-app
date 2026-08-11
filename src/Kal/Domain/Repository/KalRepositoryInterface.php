<?php

declare(strict_types=1);

namespace App\Kal\Domain\Repository;

use App\Kal\Domain\Exception\KalAlreadyExistsException;
use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Exception\KalNotFoundException;
use App\Kal\Domain\Exception\KalStateException;
use App\Kal\Domain\InviteToken;
use App\Kal\Domain\Kal;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\UlidValue;

interface KalRepositoryInterface
{
    /**
     * @throws KalAlreadyExistsException
     * @throws KalStateException
     */
    public function create(Kal $kal): void;

    /**
     * Persists scalar Kal fields already mutated on the aggregate.
     * Does not touch locales, files, clues, meetings or debate rooms.
     *
     * @throws KalNotFoundException
     * @throws KalStateException
     */
    public function update(Kal $kal): void;

    /**
     * Soft delete: marca `deleted_at` i el KAL deixa d'existir per a tothom.
     * No és idempotent — un segon intent ja no troba cap fila activa i és 404.
     *
     * @throws KalNotFoundException
     * @throws KalStateException
     * @throws InvalidArgumentException
     */
    public function delete(Kal $kal): void;

    /**
     * @throws KalNotFoundException
     * @throws KalException
     * @throws KalStateException
     */
    public function findById(UlidValue $id, UlidValue $organizerId): Kal;

    /**
     * Active (non-soft-deleted) kal by id — no organizer filter (join path).
     *
     * @throws KalNotFoundException
     * @throws KalException
     * @throws KalStateException
     */
    public function getActiveById(UlidValue $id): Kal;

    /**
     * Qui pot apuntar-s'hi no ho decideix el repositori: veure `JoinPolicy`.
     *
     * @throws KalNotFoundException
     * @throws KalException
     * @throws KalStateException
     */
    public function findByToken(UlidValue $kalId, InviteToken $inviteToken): Kal;
}
