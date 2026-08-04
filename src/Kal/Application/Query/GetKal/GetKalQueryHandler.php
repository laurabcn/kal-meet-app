<?php

declare(strict_types=1);

namespace App\Kal\Application\Query\GetKal;

use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Exception\KalNotFoundException;
use App\Kal\Domain\KalRepositoryInterface;
use App\Shared\Application\Query\QueryHandlerInterface;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\UlidValue;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetKalQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private KalRepositoryInterface $kalRepository,
    ) {
    }

    /**
     * @throws KalNotFoundException
     * @throws KalException
     * @throws InvalidArgumentException
     */
    public function __invoke(GetKalQuery $query): GetKalResponse
    {
        return new GetKalResponse(
            $this->kalRepository->findById(
                UlidValue::create($query->id),
                UlidValue::create($query->callerId),
            ),
        );
    }
}
