<?php

declare(strict_types=1);

namespace Tests\Unit\Kal\Infrastructure\Persistence;

use App\Kal\Domain\Exception\KalAlreadyMemberException;
use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Exception\KalStateException;
use App\Kal\Domain\Participation;
use App\Kal\Domain\Repository\ParticipationRepositoryInterface;
use App\Shared\Domain\ValueObject\UlidValue;

final class InMemoryParticipationRepository implements ParticipationRepositoryInterface
{
    /** @var array<string, Participation> keyed by kalId\0userId */
    private array $participations = [];

    private ?KalStateException $failure = null;

    public function failWith(KalStateException $failure): void
    {
        $this->failure = $failure;
    }

    /**
     * @throws KalAlreadyMemberException
     * @throws KalException
     */
    public function create(Participation $participation): void
    {
        if (null !== $this->failure) {
            throw $this->failure;
        }

        // Mateix repartiment que el repositori real: aquí només hi arriba la
        // cursa, perquè el cas normal el talla l'`exists()` del handler.
        $key = self::key($participation->kalId, $participation->userId);
        if (isset($this->participations[$key])) {
            throw KalAlreadyMemberException::create();
        }

        $this->participations[$key] = $participation;
    }

    /** @throws KalException */
    public function exists(UlidValue $kalId, UlidValue $userId): bool
    {
        if (null !== $this->failure) {
            throw $this->failure;
        }

        return isset($this->participations[self::key($kalId, $userId)]);
    }

    /** @return list<Participation> */
    public function all(): array
    {
        return array_values($this->participations);
    }

    private static function key(UlidValue $kalId, UlidValue $userId): string
    {
        return $kalId->value()."\0".$userId->value();
    }
}
