<?php

declare(strict_types=1);

use App\Kal\Domain\Participation;
use App\Kal\Infrastructure\Repository\MySQL\Hydrator\KalHydrator;
use App\Kal\Infrastructure\Repository\MySQL\Hydrator\KalSummaryHydrator;
use App\Kal\Infrastructure\Repository\MySQL\KalRepository;
use App\Kal\Infrastructure\Repository\MySQL\ParticipationRepository;
use App\Shared\Domain\ValueObject\UlidValue;
use App\Shared\Infrastructure\Repository\MySQLRepository;
use Doctrine\DBAL\Exception\DriverException;
use Psr\Log\NullLogger;
use Symfony\Component\Uid\Uuid;
use Tests\Integration\Kal\Infrastructure\Persistence\SupabaseConnection;
use Tests\Unit\Kal\Domain\Mother\KalMother;

// Provar la helper no prova la política: entre les dues hi ha el `using (...)`
// i, sobretot, el rol. Aquests tests corren com a `authenticated` (el rol de
// Supabase per a una usuària loguejada) perquè `postgres` és superusuari i
// salta la RLS sencera — amb ell, una política que no filtrés res passaria
// igual.
//
// El backend NO passa per aquí: usa la service_role key i salta la RLS a
// propòsit. Qui hi passa és el frontend amb supabase-js, que és qui llegeix
// aquesta taula directament.

beforeEach(function (): void {
    SupabaseConnection::begin();

    $this->connection = SupabaseConnection::get();
    $mysql = new MySQLRepository($this->connection);

    $this->memberUuid = Uuid::v4()->toRfc4122();
    $this->strangerUuid = Uuid::v4()->toRfc4122();

    $organizerId = UlidValue::generate();
    $this->memberId = UlidValue::generate();
    $strangerId = UlidValue::generate();

    SupabaseConnection::insertProfile($organizerId->value(), Uuid::v4()->toRfc4122());
    SupabaseConnection::insertProfile($this->memberId->value(), $this->memberUuid);
    SupabaseConnection::insertProfile($strangerId->value(), $this->strangerUuid);

    $this->kal = KalMother::create(organizerId: $organizerId);
    (new KalRepository($mysql, new NullLogger(), new KalHydrator(), new KalSummaryHydrator()))->create($this->kal);

    $participations = new ParticipationRepository($mysql);
    $participations->create(Participation::create(UlidValue::generate(), $this->kal->id, $this->memberId));

    // Un KAL d'algú altre amb la seva participació: el que ha de quedar fora
    // de la vista de la nostra membre.
    $otherOrganizerId = UlidValue::generate();
    SupabaseConnection::insertProfile($otherOrganizerId->value(), Uuid::v4()->toRfc4122());
    $otherKal = KalMother::create(organizerId: $otherOrganizerId);
    (new KalRepository($mysql, new NullLogger(), new KalHydrator(), new KalSummaryHydrator()))->create($otherKal);
    $participations->create(Participation::create(UlidValue::generate(), $otherKal->id, $strangerId));

    $this->visibleParticipations = fn (): array => $this->connection->fetchFirstColumn(
        'SELECT kal_id FROM participations',
    );
});

afterEach(function (): void {
    SupabaseConnection::rollBack();
});

it('lets a member read the participation rows of their kal', function (): void {
    SupabaseConnection::authenticateAs($this->memberUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(($this->visibleParticipations)())->toBe([$this->kal->id->value()]);
});

// La decisió de producte: les participants es veuen entre elles. Si algun dia
// es restringeix a «només la pròpia fila», aquest test ho ha de dir.
it('lets a member see the other participants of the same kal, not just themselves', function (): void {
    $otherMemberId = UlidValue::generate();
    SupabaseConnection::insertProfile($otherMemberId->value(), Uuid::v4()->toRfc4122());
    (new ParticipationRepository(new MySQLRepository($this->connection)))->create(
        Participation::create(UlidValue::generate(), $this->kal->id, $otherMemberId),
    );

    SupabaseConnection::authenticateAs($this->memberUuid);
    SupabaseConnection::asAuthenticatedRole();

    $userIds = $this->connection->fetchFirstColumn('SELECT user_id FROM participations ORDER BY user_id');
    $expected = [$this->memberId->value(), $otherMemberId->value()];
    sort($expected);

    expect($userIds)->toBe($expected);
});

it('hides every participation row from a stranger to the kal', function (): void {
    SupabaseConnection::authenticateAs($this->strangerUuid);
    SupabaseConnection::asAuthenticatedRole();

    // L'estranya té participació al SEU kal, o sigui que veu la seva i prou:
    // el que no ha de veure és cap fila del nostre.
    expect(($this->visibleParticipations)())->not->toContain($this->kal->id->value());
});

it('hides every participation row from an anonymous caller', function (): void {
    SupabaseConnection::authenticateAs(null);
    SupabaseConnection::asAuthenticatedRole();

    expect(($this->visibleParticipations)())->toBeEmpty();
});

it('hides the participation rows once the kal is soft-deleted', function (): void {
    $this->connection->executeStatement(
        'UPDATE kals SET deleted_at = now() WHERE id = :id',
        ['id' => $this->kal->id->value()],
    );

    SupabaseConnection::authenticateAs($this->memberUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(($this->visibleParticipations)())->toBeEmpty();
});

// Sense cap política d'INSERT, la taula és de només lectura per al client:
// apuntar-se ha de passar pel backend, que és qui valida el token d'invitació.
it('refuses a direct insert from the client', function (): void {
    SupabaseConnection::authenticateAs($this->memberUuid);
    SupabaseConnection::asAuthenticatedRole();

    // `Doctrine\DBAL\Exception` és una INTERFÍCIE a DBAL 4 i `toThrow()` només
    // fa match per classe: cal la concreta, o l'assert es converteix en una
    // comparació de missatges i passa per accident.
    expect(fn () => $this->connection->executeStatement(
        'INSERT INTO participations (id, kal_id, user_id) VALUES (:id, :kal_id, :user_id)',
        [
            'id' => UlidValue::generate()->value(),
            'kal_id' => $this->kal->id->value(),
            'user_id' => $this->memberId->value(),
        ],
    ))->toThrow(DriverException::class, 'permission denied for table participations');
});
