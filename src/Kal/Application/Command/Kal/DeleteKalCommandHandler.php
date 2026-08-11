<?php

declare(strict_types=1);

namespace App\Kal\Application\Command\Kal;

use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Exception\KalNotFoundException;
use App\Kal\Domain\Exception\KalStateException;
use App\Kal\Domain\Repository\KalRepositoryInterface;
use App\Shared\Application\Command\CommandHandlerInterface;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\UlidValue;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DeleteKalCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private KalRepositoryInterface $kalRepository,
    ) {
    }

    /**
     * @throws KalNotFoundException
     * @throws KalException
     * @throws KalStateException
     * @throws InvalidArgumentException
     */
    public function __invoke(DeleteKalCommand $command): void
    {
        $kal = $this->kalRepository->findById(
            UlidValue::create($command->id),
            UlidValue::create($command->organizerId),
        );

        $kal->delete();

        $this->kalRepository->delete($kal);
    }
}
