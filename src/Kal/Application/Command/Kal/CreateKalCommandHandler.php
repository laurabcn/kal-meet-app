<?php

declare(strict_types=1);

namespace App\Kal\Application\Command\Kal;

use App\Kal\Application\Factory\CluePayloadFactory;
use App\Kal\Domain\Clue;
use App\Kal\Domain\Clues;
use App\Kal\Domain\Exception\KalAlreadyExistsException;
use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Exception\KalFileException;
use App\Kal\Domain\Exception\KalStateException;
use App\Kal\Domain\File;
use App\Kal\Domain\Files;
use App\Kal\Domain\Kal;
use App\Kal\Domain\Locales;
use App\Kal\Domain\Meeting;
use App\Kal\Domain\Meetings;
use App\Kal\Domain\Repository\KalRepositoryInterface;
use App\Shared\Application\Command\CommandHandlerInterface;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\Locale;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use App\Shared\Domain\ValueObject\UlidValue;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateKalCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private KalRepositoryInterface $kalRepository,
    ) {
    }

    /**
     * @throws KalAlreadyExistsException
     * @throws KalException
     * @throws KalStateException
     * @throws InvalidArgumentException
     * @throws KalFileException
     */
    public function __invoke(CreateKalCommand $command): void
    {
        $kal = Kal::create(
            UlidValue::create($command->id),
            UlidValue::create($command->organizerId),
            NonEmptyStringValue::create($command->name),
            DateTime::create($command->startsOn),
            self::buildLocales($command->locales),
            self::buildFiles($command->files),
            self::buildClues($command->clues),
            null !== $command->description ? NonEmptyStringValue::create($command->description) : null,
            null !== $command->endsOn ? DateTime::create($command->endsOn) : null,
            $command->coverPath,
            self::buildMeetings($command->meetings),
        );

        $this->kalRepository->create($kal);
    }

    /**
     * @param list<mixed> $locales
     *
     * @throws KalException
     * @throws InvalidArgumentException
     */
    private static function buildLocales(array $locales): Locales
    {
        return Locales::create(array_map(
            static fn (mixed $code): Locale => Locale::fromString(CluePayloadFactory::toString($code)),
            $locales,
        ));
    }

    /**
     * @param list<mixed> $files
     *
     * @throws InvalidArgumentException
     * @throws KalFileException
     */
    private static function buildFiles(array $files): Files
    {
        return Files::create(array_map(
            static fn (mixed $data): File => CluePayloadFactory::file(CluePayloadFactory::toArray($data)),
            $files,
        ));
    }

    /**
     * Els ids de pista es generen aquí, no al domini: el `POST` d'una pista
     * solta n'ha de tornar un al client i `Clue::create()` ja no se'l inventa.
     *
     * @param list<mixed> $clues
     *
     * @throws KalException
     * @throws InvalidArgumentException
     * @throws KalFileException
     */
    private static function buildClues(array $clues): Clues
    {
        return Clues::create(array_map(
            static fn (mixed $data): Clue => CluePayloadFactory::clue(
                UlidValue::generate(),
                CluePayloadFactory::toArray($data),
            ),
            $clues,
        ));
    }

    /**
     * @param list<mixed> $meetings
     *
     * @throws KalException
     * @throws InvalidArgumentException
     */
    private static function buildMeetings(array $meetings): Meetings
    {
        return Meetings::create(array_map(
            static fn (mixed $data): Meeting => CluePayloadFactory::meeting(CluePayloadFactory::toArray($data)),
            $meetings,
        ));
    }
}
