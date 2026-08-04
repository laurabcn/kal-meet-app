<?php

declare(strict_types=1);

namespace App\Kal\Infrastructure\Repository\MySQL;

use App\Kal\Domain\Exception\KalAlreadyExistsException;
use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Exception\KalNotFoundException;
use App\Kal\Domain\Kal;
use App\Kal\Domain\KalRepositoryInterface;
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

    public function __construct(
        public private(set) MySQLRepository $repository,
        public private(set) LoggerInterface $logger,
        public private(set) KalHydrator $hydrator,
    ) {
    }

    /**
     * @throws Exception
     * @throws KalAlreadyExistsException
     * @throws KalException
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

            $connection->commit();
        } catch (UniqueConstraintViolationException $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw KalAlreadyExistsException::create();
        } catch (\Throwable $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw KalException::persistenceFailed($e);
        }
    }

    /**
     * @throws KalNotFoundException
     * @throws KalException
     * @throws Exception
     */
    public function findById(UlidValue $id, UlidValue $organizerId): Kal
    {
        $connection = $this->repository->connection();
        $kalId = $id->value();

        $data = $connection
            ->createQueryBuilder()
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where('id = :kalId')
            ->andWhere('organizer_id = :organizerId')
            ->andWhere('deleted_at IS NULL')
            ->setParameter('kalId', $kalId)
            ->setParameter('organizerId', $organizerId->value())
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

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

        $hydrated = $this->repository->hydrate([$data], $this->hydrator);

        return $hydrated[0];
    }
}
