<?php

declare(strict_types=1);

namespace Tests\Unit\Kal\Infrastructure\Persistence;

use App\Kal\Domain\Clue;
use App\Kal\Domain\Exception\KalAlreadyExistsException;
use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Exception\KalNotFoundException;
use App\Kal\Domain\Exception\KalStateException;
use App\Kal\Domain\InviteToken;
use App\Kal\Domain\Kal;
use App\Kal\Domain\KalSummary;
use App\Kal\Domain\Repository\KalRepositoryInterface;
use App\Shared\Domain\ValueObject\UlidValue;

final class InMemoryKalRepository implements KalRepositoryInterface
{
    /** @var Kal[] */
    private array $kals = [];

    /** @var array<string, true> */
    private array $deletedIds = [];

    /** @var Clue[] */
    private array $writtenClues = [];

    /** @var list<string> */
    private array $deletedClueIds = [];

    private ?KalStateException $failure = null;

    /** La propera escriptura peta, com quan cau la BD a mig `create()`. */
    public function failWith(KalStateException $failure): void
    {
        $this->failure = $failure;
    }

    /**
     * @throws KalAlreadyExistsException
     * @throws KalStateException
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
     * @throws KalStateException
     */
    public function update(Kal $kal): void
    {
        if (null !== $this->failure) {
            throw $this->failure;
        }

        $id = $kal->id->value();
        if (isset($this->deletedIds[$id]) || !isset($this->kals[$id])) {
            throw KalNotFoundException::create();
        }

        if (!$this->kals[$id]->organizerId->equals($kal->organizerId)) {
            throw KalNotFoundException::create();
        }

        $this->kals[$id] = $kal;
    }

    /**
     * @throws KalNotFoundException
     * @throws KalStateException
     */
    public function delete(Kal $kal): void
    {
        if (null !== $this->failure) {
            throw $this->failure;
        }

        $key = $kal->id->value();

        if (isset($this->deletedIds[$key]) || !isset($this->kals[$key])) {
            throw KalNotFoundException::create();
        }

        if (!$this->kals[$key]->organizerId->equals($kal->organizerId)) {
            throw KalNotFoundException::create();
        }

        $this->deletedIds[$key] = true;
    }

    /**
     * @return list<KalSummary>
     *
     * @throws KalStateException
     */
    public function findAllByOrganizer(UlidValue $organizerId): array
    {
        if (null !== $this->failure) {
            throw $this->failure;
        }

        $mine = [];
        foreach ($this->kals as $id => $kal) {
            if (isset($this->deletedIds[$id]) || !$kal->organizerId->equals($organizerId)) {
                continue;
            }

            $mine[] = $kal;
        }

        // Mateix ordre que l'SQL: comença més tard primer, desempat per id.
        usort($mine, static fn (Kal $a, Kal $b): int => [$b->startsOn->value(), $b->id->value()]
            <=> [$a->startsOn->value(), $a->id->value()]);

        return array_map(
            static fn (Kal $kal): KalSummary => new KalSummary(
                $kal->id,
                $kal->name,
                $kal->description,
                $kal->startsOn,
                $kal->endsOn,
                $kal->coverPath,
            ),
            $mine,
        );
    }

    /**
     * El doble no reescriu la col·lecció: l'agregat que el handler acaba de
     * mutar ja és el mateix objecte que hi ha desat, o sigui que la pista hi és.
     * Aquí només es registra que l'escriptura s'ha demanat, i es respecta el
     * `failWith()`.
     *
     * @throws KalStateException
     */
    public function addClue(UlidValue $kalId, Clue $clue): void
    {
        $this->writtenClues[] = $clue;

        if (null !== $this->failure) {
            throw $this->failure;
        }
    }

    /**
     * @throws KalStateException
     */
    public function updateClue(UlidValue $kalId, Clue $clue): void
    {
        $this->writtenClues[] = $clue;

        if (null !== $this->failure) {
            throw $this->failure;
        }
    }

    /**
     * @throws KalStateException
     */
    public function deleteClue(UlidValue $kalId, Clue $clue): void
    {
        $this->deletedClueIds[] = $clue->id->value();

        if (null !== $this->failure) {
            throw $this->failure;
        }
    }

    /** @return Clue[] */
    public function writtenClues(): array
    {
        return $this->writtenClues;
    }

    /** @return list<string> */
    public function deletedClueIds(): array
    {
        return $this->deletedClueIds;
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

    /**
     * Drecera per deixar un KAL ja esborrat abans del cas sota prova, sense
     * passar per `delete()` (no comprova ni propietat ni existència).
     */
    public function softDelete(string $id): void
    {
        $this->deletedIds[$id] = true;
    }

    public function isDeleted(string $id): bool
    {
        return isset($this->deletedIds[$id]);
    }

    /** @return Kal[] */
    public function all(): array
    {
        return array_values($this->kals);
    }
}
