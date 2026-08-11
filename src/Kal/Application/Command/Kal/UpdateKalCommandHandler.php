<?php

declare(strict_types=1);

namespace App\Kal\Application\Command\Kal;

use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Exception\KalNotFoundException;
use App\Kal\Domain\Exception\KalStateException;
use App\Kal\Domain\Repository\KalRepositoryInterface;
use App\Shared\Application\Command\CommandHandlerInterface;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use App\Shared\Domain\ValueObject\UlidValue;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UpdateKalCommandHandler implements CommandHandlerInterface
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
    public function __invoke(UpdateKalCommand $command): void
    {
        $kal = $this->kalRepository->findById(
            UlidValue::create($command->id),
            UlidValue::create($command->organizerId),
        );

        if ([] === $command->changes) {
            return;
        }

        $name = \array_key_exists('name', $command->changes)
            ? NonEmptyStringValue::create($command->changes['name'])
            : $kal->name;

        $description = $kal->description;
        if (\array_key_exists('description', $command->changes)) {
            $raw = $command->changes['description'];
            $description = null !== $raw ? NonEmptyStringValue::create($raw) : null;
        }

        $startsOn = \array_key_exists('startsOn', $command->changes)
            ? DateTime::create($command->changes['startsOn'])
            : $kal->startsOn;

        $endsOn = $kal->endsOn;
        if (\array_key_exists('endsOn', $command->changes)) {
            $raw = $command->changes['endsOn'];
            $endsOn = null !== $raw ? DateTime::create($raw) : null;
        }

        $coverPath = $kal->coverPath;
        if (\array_key_exists('coverPath', $command->changes)) {
            $coverPath = $command->changes['coverPath'];
        }

        $kal->update($name, $description, $startsOn, $endsOn, $coverPath);

        $this->kalRepository->update($kal);
    }
}
