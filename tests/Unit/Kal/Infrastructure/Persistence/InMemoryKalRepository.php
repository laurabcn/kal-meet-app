<?php

declare(strict_types=1);

namespace Tests\Unit\Kal\Infrastructure\Persistence;

use App\Kal\Domain\Exception\KalAlreadyExistsException;
use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Exception\KalNotFoundException;
use App\Kal\Domain\InviteToken;
use App\Kal\Domain\Kal;
use App\Kal\Domain\Repository\KalRepositoryInterface;
use App\Shared\Domain\ValueObject\UlidValue;

final class InMemoryKalRepository implements KalRepositoryInterface
{
    /** @var Kal[] */
    private array $kals = [];

    /** @var array<string, true> */
    private array $deletedIds = [];

    private ?KalException $failure = null;

    /** La propera escriptura peta, com quan cau la BD a mig `create()`. */
    public function failWith(KalException $failure): void
    {
        $this->failure = $failure;
    }

    /**
     * @throws KalAlreadyExistsException
     * @throws KalException
     */
    public function create(Kal $kal): void
    {
        if (null !== $this->failure) {
            throw $this->failure;
        }

        $id = $kal->id->value();
        if (isset($this->kals[$id])) {
            throw KalAlreadyExistsException::create();
        }

        $this->kals[$id] = $kal;
    }

    /**
     * @throws KalNotFoundException
     * @throws KalException
     */
    public function findById(UlidValue $id, UlidValue $organizerId): Kal
    {
        $kal = $this->getActiveById($id);

        if (!$kal->organizerId->equals($organizerId)) {
            throw KalNotFoundException::create();
        }

        return $kal;
    }

    /**
     * @throws KalNotFoundException
     * @throws KalException
     */
    public function getActiveById(UlidValue $id): Kal
    {
        if (null !== $this->failure) {
            throw $this->failure;
        }

        $key = $id->value();

        if (isset($this->deletedIds[$key])) {
            throw KalNotFoundException::create();
        }

        $kal = $this->kals[$key] ?? null;
        if (null === $kal) {
            throw KalNotFoundException::create();
        }

        return $kal;
    }

    /**
     * @throws KalNotFoundException
     * @throws KalException
     */
    public function findByToken(UlidValue $kalId, InviteToken $inviteToken): Kal
    {
        $kal = $this->getActiveById($kalId);

        if (!$kal->inviteToken->equals($inviteToken)) {
            throw KalNotFoundException::create();
        }

        return $kal;
    }

    /** Simula un soft delete: el KAL desapareix de `findById`, com fa `deleted_at IS NOT NULL` a la BD. */
    public function softDelete(string $id): void
    {
        $this->deletedIds[$id] = true;
    }

    /** @return Kal[] */
    public function all(): array
    {
        return array_values($this->kals);
    }
}
