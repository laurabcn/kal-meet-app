<?php

declare(strict_types=1);

namespace App\Kal\Infrastructure\Repository\MySQL;

use App\Kal\Domain\Exception\KalAlreadyMemberException;
use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Participation;
use App\Kal\Domain\Repository\ParticipationRepositoryInterface;
use App\Shared\Domain\ValueObject\UlidValue;
use App\Shared\Infrastructure\Repository\MySQLRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class ParticipationRepository implements ParticipationRepositoryInterface
{
    private const string TABLE_NAME = 'participations';

    public function __construct(
        private MySQLRepository $repository,
    ) {
    }

    /**
     * @throws KalAlreadyMemberException
     * @throws KalException
     */
    public function create(Participation $participation): void
    {
        try {
            $this->repository->connection()->insert(self::TABLE_NAME, [
                'id' => $participation->id->value(),
                'kal_id' => $participation->kalId->value(),
                'user_id' => $participation->userId->value(),
                'joined_at' => $participation->joinedAt->value(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Xarxa per a la cursa: dues peticions simultànies passen totes dues
            // l'`exists()` del handler i només una guanya l'índex únic.
            throw KalAlreadyMemberException::create();
        } catch (\Throwable $e) {
            throw KalException::persistenceFailed($e);
        }
    }

    /** @throws KalException */
    public function exists(UlidValue $kalId, UlidValue $userId): bool
    {
        try {
            $id = $this->repository->connection()
                ->createQueryBuilder()
                ->select('id')
                ->from(self::TABLE_NAME)
                ->where('kal_id = :kalId')
                ->andWhere('user_id = :userId')
                ->setParameter('kalId', $kalId->value())
                ->setParameter('userId', $userId->value())
                ->setMaxResults(1)
                ->executeQuery()
                ->fetchOne();
        } catch (\Throwable $e) {
            throw KalException::persistenceFailed($e);
        }

        return false !== $id && null !== $id;
    }
}
