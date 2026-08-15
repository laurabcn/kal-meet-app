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
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\Locale;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use App\Shared\Domain\ValueObject\UlidValue;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UpdateClueCommandHandler implements CommandHandlerInterface
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
    public function __invoke(UpdateClueCommand $command): void
    {
        $kalId = UlidValue::create($command->kalId);
        $kal = $this->kalRepository->findById($kalId, UlidValue::create($command->organizerId));
        $clueId = UlidValue::create($command->clueId);

        // Un patch buit no és un error, però tampoc mou `updatedAt`: la pista
        // no ha canviat. Es comprova que existeixi abans de tornar.
        if ([] === $command->changes) {
            $kal->clues->get($clueId);

            return;
        }

        $current = $kal->clues->get($clueId);

        $name = \array_key_exists('name', $command->changes)
            ? NonEmptyStringValue::create($command->changes['name'])
            : $current->name;

        $description = $current->description;
        if (\array_key_exists('description', $command->changes)) {
            $raw = $command->changes['description'];
            $description = null !== $raw ? NonEmptyStringValue::create($raw) : null;
        }

        $startsOn = \array_key_exists('startsOn', $command->changes)
            ? DateTime::create($command->changes['startsOn'])
            : $current->startsOn;

        $endsOn = \array_key_exists('endsOn', $command->changes)
            ? DateTime::create($command->changes['endsOn'])
            : $current->endsOn;

        $locale = \array_key_exists('locale', $command->changes)
            ? Locale::fromString($command->changes['locale'])
            : $current->locale;

        $updated = $kal->updateClue($clueId, $name, $description, $startsOn, $endsOn, $locale);

        $this->kalRepository->updateClue($kalId, $updated);
    }
}
