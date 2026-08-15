<?php

declare(strict_types=1);

namespace App\Kal\Application\Command\Clue;

use App\Kal\Application\Factory\CluePayloadFactory;
use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Exception\KalFileException;
use App\Kal\Domain\Exception\KalNotFoundException;
use App\Kal\Domain\Exception\KalStateException;
use App\Kal\Domain\Repository\KalRepositoryInterface;
use App\Shared\Application\Command\CommandHandlerInterface;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\UlidValue;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateClueCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private KalRepositoryInterface $kalRepository,
    ) {
    }

    /**
     * @throws KalNotFoundException
     * @throws KalException
     * @throws KalStateException
     * @throws KalFileException
     * @throws InvalidArgumentException
     */
    public function __invoke(CreateClueCommand $command): void
    {
        $kalId = UlidValue::create($command->kalId);

        // Carregar l'agregat NO és opcional: és qui comprova que sigui teu, que
        // les dates caiguin dins del KAL i que el locale hi estigui habilitat.
        $kal = $this->kalRepository->findById($kalId, UlidValue::create($command->organizerId));

        $clue = CluePayloadFactory::clue(UlidValue::create($command->clueId), $command->payload);
        $kal->addClue($clue);

        $this->kalRepository->addClue($kalId, $clue);
    }
}
