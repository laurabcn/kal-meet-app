<?php

declare(strict_types=1);

namespace App\Kal\Application\Command\Kal;

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
final readonly class ReplaceKalFileCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private KalRepositoryInterface $kalRepository,
    ) {
    }

    /**
     * @throws KalNotFoundException
     * @throws KalException
     * @throws KalFileException
     * @throws KalStateException
     * @throws InvalidArgumentException
     */
    public function __invoke(ReplaceKalFileCommand $command): void
    {
        // Abans que el payload: qui no és l'organitzadora rep 404 encara que
        // el fitxer que envia sigui invàlid.
        $kal = $this->kalRepository->findById(
            UlidValue::create($command->kalId),
            UlidValue::create($command->organizerId),
        );

        if (!$kal->replaceFile(CluePayloadFactory::file($command->payload))) {
            return;
        }

        $this->kalRepository->replaceFile($kal);
    }
}
