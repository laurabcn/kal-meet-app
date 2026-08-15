<?php

declare(strict_types=1);

namespace App\Kal\Application\Query\ListKals;

use App\Kal\Domain\Exception\KalStateException;
use App\Kal\Domain\Repository\KalRepositoryInterface;
use App\Shared\Application\Query\QueryHandlerInterface;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\UlidValue;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListKalsQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private KalRepositoryInterface $kalRepository,
    ) {
    }

    /**
     * @throws KalStateException
     * @throws InvalidArgumentException
     */
    public function __invoke(ListKalsQuery $query): ListKalsResponse
    {
        // Sense KALs no és cap error: una organitzadora nova en té zero.
        return new ListKalsResponse(
            $this->kalRepository->findAllByOrganizer(UlidValue::create($query->organizerId)),
        );
    }
}
