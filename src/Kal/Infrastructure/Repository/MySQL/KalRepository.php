<?php

declare(strict_types=1);

namespace App\Kal\Infrastructure\Repository\MySQL;

use App\Kal\Domain\Clue;
use App\Kal\Domain\Exception\ClueNotFoundException;
use App\Kal\Domain\Exception\KalAlreadyExistsException;
use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Exception\KalNotFoundException;
use App\Kal\Domain\Exception\KalStateException;
use App\Kal\Domain\InviteToken;
use App\Kal\Domain\Kal;
use App\Kal\Domain\KalSummary;
use App\Kal\Domain\Repository\KalRepositoryInterface;
use App\Kal\Infrastructure\Repository\MySQL\Hydrator\KalHydrator;
use App\Kal\Infrastructure\Repository\MySQL\Hydrator\KalSummaryHydrator;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\UlidValue;
use App\Shared\Infrastructure\Repository\MySQLRepository;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Log\LoggerInterface;

final readonly class KalRepository implements KalRepositoryInterface
{
    private const string TABLE_NAME = 'kals';
    private const string TABLE_LOCALES = 'kal_locales';
    private const string TABLE_FILES = 'kal_files';
    private const string TABLE_CLUES = 'clues';
    private const string TABLE_MEETINGS = 'meetings';
    private const string TABLE_DEBATE_ROOMS = 'debate_rooms';

    /** Filles que el soft delete del KAL marca amb ell. */
    private const array CHILD_TABLES = [
        self::TABLE_LOCALES,
        self::TABLE_FILES,
        self::TABLE_CLUES,
        self::TABLE_MEETINGS,
        self::TABLE_DEBATE_ROOMS,
    ];

    public function __construct(
        public private(set) MySQLRepository $repository,
        public private(set) LoggerInterface $logger,
        public private(set) KalHydrator $hydrator,
        public private(set) KalSummaryHydrator $summaryHydrator,
    ) {
    }

    /**
     * @throws Exception
     * @throws KalAlreadyExistsException
     * @throws KalStateException
     */
    public function create(Kal $kal): void
    {
        $connection = $this->repository->connection();
        $data = $this->hydrator->extract($kal);

        $connection->beginTransaction();
        try {
            $connection->insert(self::TABLE_NAME, $data['kal']);

            foreach ($data['locales'] as $locale) {
                $connection->insert(self::TABLE_LOCALES, $locale);
            }

            foreach ($data['files'] as $file) {
                $connection->insert(self::TABLE_FILES, $file);
            }

            foreach ($data['clues'] as $clue) {
                $connection->insert(self::TABLE_CLUES, $clue);
            }

            foreach ($data['meetings'] as $meeting) {
                $connection->insert(self::TABLE_MEETINGS, $meeting);
            }

            // Dins de la mateixa transacció a propòsit: un KAL sense aula no es
            // pot llegir, o sigui que no pot existir ni un instant.
            $connection->insert(self::TABLE_DEBATE_ROOMS, $data['debate_room']);

            $connection->commit();
        } catch (UniqueConstraintViolationException $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            // Només el conflicte d'identitat del KAL és 409. Qualsevol altre
            // unique (meetings, debate_rooms, …) és estat trencat / 500.
            if (str_contains($e->getMessage(), 'kals_pkey')) {
                throw KalAlreadyExistsException::create();
            }

            throw KalStateException::persistenceFailed($e);
        } catch (\Throwable $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw KalStateException::persistenceFailed($e);
        }
    }

    /**
     * @throws KalNotFoundException
     * @throws KalStateException
     * @throws Exception
     */
    public function update(Kal $kal): void
    {
        $connection = $this->repository->connection();
        $row = $this->hydrator->extract($kal)['kal'];

        try {
            $affected = $connection->executeStatement(
                'UPDATE '.self::TABLE_NAME.'
                 SET name = :name,
                     description = :description,
                     starts_on = :starts_on,
                     ends_on = :ends_on,
                     cover_path = :cover_path,
                     updated_at = :updated_at
                 WHERE id = :id
                   AND organizer_id = :organizer_id
                   AND deleted_at IS NULL',
                [
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'starts_on' => $row['starts_on'],
                    'ends_on' => $row['ends_on'],
                    'cover_path' => $row['cover_path'],
                    'updated_at' => $row['updated_at'],
                    'id' => $row['id'],
                    'organizer_id' => $row['organizer_id'],
                ],
            );

            if (0 === $affected) {
                throw KalNotFoundException::create();
            }
        } catch (KalNotFoundException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw KalStateException::persistenceFailed($e);
        }
    }

    /**
     * @throws KalNotFoundException
     * @throws KalStateException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function delete(Kal $kal): void
    {
        $connection = $this->repository->connection();

        // Arribar-hi sense marca vol dir que ningú ha cridat `Kal::delete()`:
        // escriure-hi null desmarcaria el KAL en comptes d'esborrar-lo.
        $deletedAt = $kal->deletedAt?->value();
        if (null === $deletedAt) {
            throw KalStateException::invalidKal();
        }

        $updatedAt = $kal->updatedAt?->value() ?? $kal->createdAt->value();

        $connection->beginTransaction();
        try {
            $affected = $connection->executeStatement(
                'UPDATE '.self::TABLE_NAME.'
                 SET deleted_at = :deleted_at,
                     updated_at = :updated_at
                 WHERE id = :id
                   AND organizer_id = :organizer_id
                   AND deleted_at IS NULL',
                [
                    'deleted_at' => $deletedAt,
                    'updated_at' => $updatedAt,
                    'id' => $kal->id->value(),
                    'organizer_id' => $kal->organizerId->value(),
                ],
            );

            // Ni existeix, ni és seu, ni ja estava esborrat: els tres casos són
            // el mateix 404, com a la resta del CRUD (no filtrem existència).
            if (0 === $affected) {
                throw KalNotFoundException::create();
            }

            // Tot l'arbre cau amb l'arrel i a la mateixa transacció: un KAL
            // esborrat amb filles vives seria un estat que ningú sap llegir.
            foreach (self::CHILD_TABLES as $table) {
                $connection->executeStatement(
                    'UPDATE '.$table.'
                     SET deleted_at = :deleted_at
                     WHERE kal_id = :kal_id
                       AND deleted_at IS NULL',
                    ['deleted_at' => $deletedAt, 'kal_id' => $kal->id->value()],
                );
            }

            $connection->commit();
        } catch (KalNotFoundException $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $e;
        } catch (\Throwable $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw KalStateException::persistenceFailed($e);
        }
    }

    /**
     * @throws KalStateException
     * @throws Exception
     */
    public function addClue(UlidValue $kalId, Clue $clue): void
    {
        $connection = $this->repository->connection();
        $id = $kalId->value();

        $connection->beginTransaction();
        try {
            // La pista PRIMER: `meetings_clue_fk` apunta cap a `clues`, o sigui
            // que la seva reunió no pot existir abans que ella.
            $connection->insert(self::TABLE_CLUES, KalHydrator::extractClue($id, $clue));
            $connection->insert(
                self::TABLE_MEETINGS,
                KalHydrator::extractMeeting($id, $clue->meeting, $clue->id->value()),
            );

            $connection->commit();
        } catch (\Throwable $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw KalStateException::persistenceFailed($e);
        }
    }

    /**
     * @throws ClueNotFoundException
     * @throws KalStateException
     * @throws Exception
     */
    public function updateClue(UlidValue $kalId, Clue $clue): void
    {
        $connection = $this->repository->connection();
        $row = KalHydrator::extractClue($kalId->value(), $clue);

        try {
            $affected = $connection->executeStatement(
                'UPDATE '.self::TABLE_CLUES.'
                 SET name = :name,
                     description = :description,
                     starts_on = :starts_on,
                     ends_on = :ends_on,
                     locale = :locale,
                     updated_at = :updated_at
                 WHERE id = :id
                   AND kal_id = :kal_id
                   AND deleted_at IS NULL',
                [
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'starts_on' => $row['starts_on'],
                    'ends_on' => $row['ends_on'],
                    'locale' => $row['locale'],
                    'updated_at' => $row['updated_at'],
                    'id' => $row['id'],
                    'kal_id' => $row['kal_id'],
                ],
            );

            if (0 === $affected) {
                throw ClueNotFoundException::create();
            }
        } catch (ClueNotFoundException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw KalStateException::persistenceFailed($e);
        }
    }

    /**
     * @throws ClueNotFoundException
     * @throws KalStateException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function deleteClue(UlidValue $kalId, Clue $clue): void
    {
        $connection = $this->repository->connection();
        $now = DateTime::now()->value();
        $id = $kalId->value();
        $clueId = $clue->id->value();

        $connection->beginTransaction();
        try {
            $affected = $connection->executeStatement(
                'UPDATE '.self::TABLE_CLUES.'
                 SET deleted_at = :deleted_at,
                     updated_at = :updated_at
                 WHERE id = :id
                   AND kal_id = :kal_id
                   AND deleted_at IS NULL',
                ['deleted_at' => $now, 'updated_at' => $now, 'id' => $clueId, 'kal_id' => $id],
            );

            if (0 === $affected) {
                throw ClueNotFoundException::create();
            }

            // La reunió d'una pista no sobreviu a la pista: és seva, no del KAL.
            $connection->executeStatement(
                'UPDATE '.self::TABLE_MEETINGS.'
                 SET deleted_at = :deleted_at
                 WHERE clue_id = :clue_id
                   AND deleted_at IS NULL',
                ['deleted_at' => $now, 'clue_id' => $clueId],
            );

            $connection->commit();
        } catch (ClueNotFoundException $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $e;
        } catch (\Throwable $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw KalStateException::persistenceFailed($e);
        }
    }

    /**
     * @return list<KalSummary>
     *
     * @throws KalStateException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function findAllByOrganizer(UlidValue $organizerId): array
    {
        $connection = $this->repository->connection();

        try {
            $rows = $connection
                ->createQueryBuilder()
                ->select('id', 'name', 'description', 'starts_on', 'ends_on', 'cover_path')
                ->from(self::TABLE_NAME)
                ->where('organizer_id = :organizerId')
                ->andWhere('deleted_at IS NULL')
                ->orderBy('starts_on', 'DESC')
                // Desempat estable: sense ell, dos KALs que comencen el mateix
                // dia poden sortir en ordre diferent a cada crida.
                ->addOrderBy('id', 'DESC')
                ->setParameter('organizerId', $organizerId->value())
                ->executeQuery()
                ->fetchAllAssociative();
        } catch (\Throwable $e) {
            throw KalStateException::persistenceFailed($e);
        }

        return array_map($this->summaryHydrator->hydrate(...), $rows);
    }

    /**
     * @throws KalNotFoundException
     * @throws KalException
     * @throws KalStateException
     * @throws Exception
     */
    public function findById(UlidValue $id, UlidValue $organizerId): Kal
    {
        return $this->loadActive($id, $organizerId);
    }

    /**
     * @throws KalNotFoundException
     * @throws KalException
     * @throws KalStateException
     * @throws Exception
     */
    public function getActiveById(UlidValue $id): Kal
    {
        return $this->loadActive($id, null);
    }

    /**
     * @throws KalNotFoundException
     * @throws KalException
     * @throws KalStateException
     * @throws Exception
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
     * @throws KalNotFoundException
     * @throws KalException
     * @throws KalStateException
     * @throws Exception
     */
    private function loadActive(UlidValue $id, ?UlidValue $organizerId): Kal
    {
        $connection = $this->repository->connection();
        $kalId = $id->value();

        $query = $connection
            ->createQueryBuilder()
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where('id = :kalId')
            ->andWhere('deleted_at IS NULL')
            ->setParameter('kalId', $kalId)
            ->setMaxResults(1);

        if (null !== $organizerId) {
            $query
                ->andWhere('organizer_id = :organizerId')
                ->setParameter('organizerId', $organizerId->value());
        }

        $data = $query->executeQuery()->fetchAssociative();

        if (!$data) {
            throw KalNotFoundException::create();
        }

        /** @var list<string> $locales */
        $locales = $connection
            ->createQueryBuilder()
            ->select('locale')
            ->from(self::TABLE_LOCALES)
            ->where('kal_id = :kalId')
            ->andWhere('deleted_at IS NULL')
            ->setParameter('kalId', $kalId)
            ->executeQuery()
            ->fetchFirstColumn();

        // Sense locales no es pot reconstitir l'agregat (invariant ≥1).
        // Els KALs creats abans de persistir kal_locales queden il·legibles.
        if ([] === $locales) {
            throw KalNotFoundException::create();
        }

        $data['locales'] = $locales;

        $data['files'] = $connection
            ->createQueryBuilder()
            ->select('*')
            ->from(self::TABLE_FILES)
            ->where('kal_id = :kalId')
            ->andWhere('deleted_at IS NULL')
            ->setParameter('kalId', $kalId)
            ->executeQuery()
            ->fetchAllAssociative();

        $data['clues'] = $connection
            ->createQueryBuilder()
            ->select('*')
            ->from(self::TABLE_CLUES)
            ->where('kal_id = :kalId')
            ->andWhere('deleted_at IS NULL')
            // Amb pistes afegides més tard, sense ORDER BY l'ordre deixa de ser
            // estable entre crides. `id` desempata les que comparteixen data.
            ->orderBy('starts_on', 'ASC')
            ->addOrderBy('id', 'ASC')
            ->setParameter('kalId', $kalId)
            ->executeQuery()
            ->fetchAllAssociative();

        $data['meetings'] = $connection
            ->createQueryBuilder()
            ->select('*')
            ->from(self::TABLE_MEETINGS)
            ->where('kal_id = :kalId')
            ->andWhere('deleted_at IS NULL')
            ->setParameter('kalId', $kalId)
            ->executeQuery()
            ->fetchAllAssociative();

        $data['debate_rooms'] = $connection
            ->createQueryBuilder()
            ->select('*')
            ->from(self::TABLE_DEBATE_ROOMS)
            ->where('kal_id = :kalId')
            ->andWhere('deleted_at IS NULL')
            ->setParameter('kalId', $kalId)
            ->executeQuery()
            ->fetchAllAssociative();

        $hydrated = $this->repository->hydrate([$data], $this->hydrator);

        return $hydrated[0];
    }
}
