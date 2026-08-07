<?php

declare(strict_types=1);

namespace Tests\Integration\Rls;

use App\Kal\Domain\Participation;
use App\Kal\Infrastructure\Repository\MySQL\Hydrator\KalHydrator;
use App\Kal\Infrastructure\Repository\MySQL\KalRepository;
use App\Kal\Infrastructure\Repository\MySQL\ParticipationRepository;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\UlidValue;
use App\Shared\Infrastructure\Repository\MySQLRepository;
use Doctrine\DBAL\Connection;
use Psr\Log\NullLogger;
use Symfony\Component\Uid\Uuid;
use Tests\Integration\Kal\Infrastructure\Persistence\SupabaseConnection;
use Tests\Unit\Kal\Domain\Mother\ClueMother;
use Tests\Unit\Kal\Domain\Mother\CluesMother;
use Tests\Unit\Kal\Domain\Mother\FileMother;
use Tests\Unit\Kal\Domain\Mother\FilesMother;
use Tests\Unit\Kal\Domain\Mother\KalMother;
use Tests\Unit\Kal\Domain\Mother\MeetingMother;
use Tests\Unit\Kal\Domain\Mother\MeetingsMother;

/**
 * L'escenari que comparteixen els tests de polítiques: un KAL amb les seves
 * files filles, tres identitats (organitzadora, membre, estranya) i les dues
 * pistes que fan falta per provar `is_clue_released` — una de publicada i una
 * de futura.
 *
 * Viu en una classe i no en funcions globals dins d'un fitxer de test: dos
 * fitxers que declaressin el mateix nom petarien amb un fatal de redeclaració.
 */
final readonly class RlsFixture
{
    private function __construct(
        public string $organizerUuid,
        public string $memberUuid,
        public string $strangerUuid,
        public string $organizerId,
        public string $kalId,
        public string $releasedClueId,
        public string $unreleasedClueId,
        public string $kalMeetingId,
        public string $releasedClueMeetingId,
        public string $unreleasedClueMeetingId,
        public string $debateRoomId,
    ) {
    }

    public static function create(Connection $connection): self
    {
        $mysql = new MySQLRepository($connection);

        $organizerUuid = Uuid::v4()->toRfc4122();
        $memberUuid = Uuid::v4()->toRfc4122();
        $strangerUuid = Uuid::v4()->toRfc4122();

        $organizerId = UlidValue::generate();
        $memberId = UlidValue::generate();
        $strangerId = UlidValue::generate();

        SupabaseConnection::insertProfile($organizerId->value(), $organizerUuid);
        SupabaseConnection::insertProfile($memberId->value(), $memberUuid);
        SupabaseConnection::insertProfile($strangerId->value(), $strangerUuid);

        // `is_clue_released` compara `starts_on <= now()`, o sigui que la
        // frontera és el rellotge: una data fixa al passat i una al futur
        // llunyà mantenen el test determinista sense congelar el temps.
        $released = ClueMother::create(
            startsOn: DateTime::create('2026-08-01 00:00:00'),
            endsOn: DateTime::create('2026-08-08 00:00:00'),
            name: 'Released round',
        );
        $unreleased = ClueMother::create(
            startsOn: DateTime::create('2099-01-01 00:00:00'),
            endsOn: DateTime::create('2099-01-08 00:00:00'),
            name: 'Future round',
        );

        $kalMeeting = MeetingMother::create(scheduledAt: DateTime::create('2026-08-15 18:00:00'));

        $kal = KalMother::create(
            files: FilesMother::of(FileMother::create()),
            clues: CluesMother::of($released, $unreleased),
            startsOn: DateTime::create('2026-08-01 00:00:00'),
            organizerId: $organizerId,
            meetings: MeetingsMother::of($kalMeeting),
        );

        (new KalRepository($mysql, new NullLogger(), new KalHydrator()))->create($kal);
        (new ParticipationRepository($mysql))->create(
            Participation::create(UlidValue::generate(), $kal->id, $memberId),
        );

        // L'aula ja ve amb el KAL: forma part de l'agregat i s'escriu a la
        // mateixa transacció, o sigui que no cal (ni es pot) inserir-la a mà.
        $debateRoomId = $kal->debateRoom->id->value();

        return new self(
            $organizerUuid,
            $memberUuid,
            $strangerUuid,
            $organizerId->value(),
            $kal->id->value(),
            $released->id->value(),
            $unreleased->id->value(),
            $kalMeeting->id->value(),
            $released->meeting->id->value(),
            $unreleased->meeting->id->value(),
            $debateRoomId,
        );
    }
}
