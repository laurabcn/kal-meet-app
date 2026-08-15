<?php

declare(strict_types=1);

namespace App\Kal\Application\Command\Clue;

use App\Kal\Domain\Exception\ClueNotFoundException;
use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Exception\KalNotFoundException;
use App\Kal\Domain\Exception\KalStateException;
use App\Kal\Domain\Repository\KalRepositoryInterface;
use App\Shared\Application\Command\CommandHandlerInterface;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\UlidValue;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DeleteClueCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private KalRepositoryInterface $kalRepository,
    ) {
    }

    /**
     * @throws KalNotFoundException
     * @throws ClueNotFoundException
     * @throws KalException
     * @throws KalStateException
     * @throws InvalidArgumentException
     */
    public function __invoke(DeleteClueCommand $command): void
    {
        $kalId = UlidValue::create($command->kalId);
        $kal = $this->kalRepository->findById($kalId, UlidValue::create($command->organizerId));

        // L'agregat torna la pista retirada perquè la seva reunió cau amb ella
        // i qui persisteix necessita saber-ne l'id.
        $removed = $kal->removeClue(UlidValue::create($command->clueId));

        $this->kalRepository->deleteClue($kalId, $removed);
    }
}
