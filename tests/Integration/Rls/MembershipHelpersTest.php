<?php

declare(strict_types=1);

use App\Kal\Domain\Participation;
use App\Kal\Infrastructure\Repository\MySQL\Hydrator\KalHydrator;
use App\Kal\Infrastructure\Repository\MySQL\Hydrator\KalSummaryHydrator;
use App\Kal\Infrastructure\Repository\MySQL\KalRepository;
use App\Kal\Infrastructure\Repository\MySQL\ParticipationRepository;
use App\Shared\Domain\ValueObject\UlidValue;
use App\Shared\Infrastructure\Repository\MySQLRepository;
use Psr\Log\NullLogger;
use Symfony\Component\Uid\Uuid;
use Tests\Integration\Kal\Infrastructure\Persistence\SupabaseConnection;
use Tests\Unit\Kal\Domain\Mother\KalMother;

// `is_kal_member` i `is_kal_organizer` són el cor de la RLS: gairebé totes les
// polítiques de l'esquema hi deleguen, o sigui que si una d'elles menteix,
// menteixen totes alhora. Fins ara no hi havia res que les provés — i
// `is_kal_member` va viure un temps com a stub que només reconeixia
// l'organitzadora, cosa que cap test hauria detectat.
//
// Es proven cridant la funció directament (no cal canviar de rol: només
// llegeixen `auth.uid()`, que ve de `request.jwt.claims`). Les polítiques que
// les fan servir es proven a part, a ParticipationsPolicyTest.

beforeEach(function (): void {
    SupabaseConnection::begin();

    $this->connection = SupabaseConnection::get();
    $mysql = new MySQLRepository($this->connection);

    // external_id ha de ser un uuid de veritat: les helpers el comparen amb
    // `auth.uid()::text`, i auth.uid() fa un cast a uuid del claim `sub`.
    $this->organizerUuid = Uuid::v4()->toRfc4122();
    $this->memberUuid = Uuid::v4()->toRfc4122();
    $this->strangerUuid = Uuid::v4()->toRfc4122();

    $organizerId = UlidValue::generate();
    $memberId = UlidValue::generate();

    SupabaseConnection::insertProfile($organizerId->value(), $this->organizerUuid);
    SupabaseConnection::insertProfile($memberId->value(), $this->memberUuid);
    SupabaseConnection::insertProfile(UlidValue::generate()->value(), $this->strangerUuid);

    $this->kal = KalMother::create(organizerId: $organizerId);
    (new KalRepository($mysql, new NullLogger(), new KalHydrator(), new KalSummaryHydrator()))->create($this->kal);

    (new ParticipationRepository($mysql))->create(
        Participation::create(UlidValue::generate(), $this->kal->id, $memberId),
    );

    $this->isMember = fn (): bool => (bool) $this->connection->fetchOne(
        'SELECT is_kal_member(:id)',
        ['id' => $this->kal->id->value()],
    );
    $this->isOrganizer = fn (): bool => (bool) $this->connection->fetchOne(
        'SELECT is_kal_organizer(:id)',
        ['id' => $this->kal->id->value()],
    );
    $this->softDeleteKal = fn () => $this->connection->executeStatement(
        'UPDATE kals SET deleted_at = now() WHERE id = :id',
        ['id' => $this->kal->id->value()],
    );
});

afterEach(function (): void {
    SupabaseConnection::rollBack();
});

it('counts the organizer as a member', function (): void {
    SupabaseConnection::authenticateAs($this->organizerUuid);

    expect(($this->isMember)())->toBeTrue();
});

// La raó de ser de la migració de participations: abans d'ella això era false.
it('counts a participant as a member', function (): void {
    SupabaseConnection::authenticateAs($this->memberUuid);

    expect(($this->isMember)())->toBeTrue();
});

it('does not count a stranger as a member', function (): void {
    SupabaseConnection::authenticateAs($this->strangerUuid);

    expect(($this->isMember)())->toBeFalse();
});

it('does not count an anonymous caller as a member', function (): void {
    SupabaseConnection::authenticateAs(null);

    expect(($this->isMember)())->toBeFalse();
});

// El soft delete no esborra cap participació: si la helper no filtrés per
// `deleted_at`, un KAL esborrat seguiria sent llegible per les seves membres.
it('drops membership when the kal is soft-deleted, for the organizer', function (): void {
    SupabaseConnection::authenticateAs($this->organizerUuid);
    ($this->softDeleteKal)();

    expect(($this->isMember)())->toBeFalse();
});

it('drops membership when the kal is soft-deleted, for a participant', function (): void {
    SupabaseConnection::authenticateAs($this->memberUuid);
    ($this->softDeleteKal)();

    expect(($this->isMember)())->toBeFalse();
});

it('reports no membership for a kal that does not exist', function (): void {
    SupabaseConnection::authenticateAs($this->organizerUuid);

    $exists = (bool) $this->connection->fetchOne(
        'SELECT is_kal_member(:id)',
        ['id' => UlidValue::generate()->value()],
    );

    expect($exists)->toBeFalse();
});

it('counts the organizer as organizer', function (): void {
    SupabaseConnection::authenticateAs($this->organizerUuid);

    expect(($this->isOrganizer)())->toBeTrue();
});

// La distinció que separa les polítiques `*_select_member` de les
// `*_insert_organizer`: una participant és membre, però no organitzadora.
it('does not count a participant as organizer', function (): void {
    SupabaseConnection::authenticateAs($this->memberUuid);

    expect(($this->isOrganizer)())->toBeFalse();
});

it('drops organizer status when the kal is soft-deleted', function (): void {
    SupabaseConnection::authenticateAs($this->organizerUuid);
    ($this->softDeleteKal)();

    expect(($this->isOrganizer)())->toBeFalse();
});
