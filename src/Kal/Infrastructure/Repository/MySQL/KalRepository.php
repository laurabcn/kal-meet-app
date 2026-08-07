<?php

declare(strict_types=1);

namespace App\Kal\Infrastructure\Repository\MySQL;

use App\Kal\Domain\Exception\KalAlreadyExistsException;
use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Exception\KalNotFoundException;
use App\Kal\Domain\Exception\KalStateException;
use App\Kal\Domain\InviteToken;
use App\Kal\Domain\Kal;
use App\Kal\Domain\Repository\KalRepositoryInterface;
use App\Kal\Infrastructure\Repository\MySQL\Hydrator\KalHydrator;
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

    public function __construct(
        public private(set) MySQLRepository $repository,
        public private(set) LoggerInterface $logger,
        public private(set) KalHydrator $hydrator,
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
            ->setParameter('kalId', $kalId)
            ->executeQuery()
            ->fetchAllAssociative();
        $data['clues'] = $connection
            ->createQueryBuilder()
            ->select('*')
            ->from(self::TABLE_CLUES)
            ->where('kal_id = :kalId')
            ->setParameter('kalId', $kalId)
            ->executeQuery()
            ->fetchAllAssociative();
        $data['meetings'] = $connection
            ->createQueryBuilder()
            ->select('*')
            ->from(self::TABLE_MEETINGS)
            ->where('kal_id = :kalId')
            ->setParameter('kalId', $kalId)
            ->executeQuery()
            ->fetchAllAssociative();
        $data['debate_rooms'] = $connection
            ->createQueryBuilder()
            ->select('*')
            ->from(self::TABLE_DEBATE_ROOMS)
            ->where('kal_id = :kalId')
            ->setParameter('kalId', $kalId)
            ->executeQuery()
            ->fetchAllAssociative();

        $hydrated = $this->repository->hydrate([$data], $this->hydrator);

        return $hydrated[0];
    }
}
