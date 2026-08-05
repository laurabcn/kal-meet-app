<?php

declare(strict_types=1);

use App\Shared\Domain\ValueObject\UlidValue;
use Doctrine\DBAL\Exception\DriverException;
use Tests\Integration\Kal\Infrastructure\Persistence\SupabaseConnection;
use Tests\Integration\Rls\RlsFixture;

// Polítiques de `kals` i les seves filles directes (`kal_locales`, `kal_files`).
// Tot corre com a `authenticated`: `postgres` és superusuari i salta la RLS, o
// sigui que sense canviar de rol una política que no filtrés res passaria igual.

beforeEach(function (): void {
    SupabaseConnection::begin();

    $this->connection = SupabaseConnection::get();
    $this->fixture = RlsFixture::create($this->connection);

    $this->visibleKals = fn (): array => $this->connection->fetchFirstColumn('SELECT id FROM kals');
});

afterEach(function (): void {
    SupabaseConnection::rollBack();
});

it('lets a member read the kal', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->memberUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(($this->visibleKals)())->toBe([$this->fixture->kalId]);
});

it('lets the organizer read the kal', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->organizerUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(($this->visibleKals)())->toBe([$this->fixture->kalId]);
});

it('hides the kal from a stranger', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->strangerUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(($this->visibleKals)())->toBeEmpty();
});

it('hides the kal from an anonymous caller', function (): void {
    SupabaseConnection::authenticateAs(null);
    SupabaseConnection::asAuthenticatedRole();

    expect(($this->visibleKals)())->toBeEmpty();
});

// El soft delete és l'únic esborrat que hi ha: si la política no el filtrés, un
// KAL "esborrat" seguiria sent llegible per tothom qui hi era.
it('hides a soft-deleted kal even from its organizer', function (): void {
    $this->connection->executeStatement(
        'UPDATE kals SET deleted_at = now() WHERE id = :id',
        ['id' => $this->fixture->kalId],
    );

    SupabaseConnection::authenticateAs($this->fixture->organizerUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(($this->visibleKals)())->toBeEmpty();
});

it('lets a client create a kal it will organize', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->organizerUuid);
    SupabaseConnection::asAuthenticatedRole();

    $id = UlidValue::generate()->value();
    $this->connection->insert('kals', [
        'id' => $id,
        'organizer_id' => $this->fixture->organizerId,
        'name' => 'Mine',
        'starts_on' => '2026-09-01 00:00:00',
        'invite_token' => UlidValue::generate()->value(),
    ]);

    expect($this->connection->fetchOne('SELECT count(*) FROM kals WHERE id = :id', ['id' => $id]))->toBe(1);
});

// La política d'INSERT compara `organizer_id` amb qui fa la crida: sense això
// qualsevol podria crear un KAL a nom d'una altra organitzadora.
it('refuses a kal created on behalf of someone else', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->strangerUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(fn () => $this->connection->insert('kals', [
        'id' => UlidValue::generate()->value(),
        'organizer_id' => $this->fixture->organizerId,
        'name' => 'Not mine',
        'starts_on' => '2026-09-01 00:00:00',
        'invite_token' => UlidValue::generate()->value(),
    ]))->toThrow(DriverException::class, 'row-level security policy');
});

it('lets the organizer rename their kal', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->organizerUuid);
    SupabaseConnection::asAuthenticatedRole();

    $this->connection->executeStatement(
        'UPDATE kals SET name = :name WHERE id = :id',
        ['name' => 'Renamed', 'id' => $this->fixture->kalId],
    );

    expect($this->connection->fetchOne('SELECT name FROM kals WHERE id = :id', ['id' => $this->fixture->kalId]))
        ->toBe('Renamed');
});

// Un UPDATE que no passa el `using` no peta: simplement no afecta cap fila.
// Per això s'assereix el valor, no l'absència d'excepció.
it('does not let a member rename the kal', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->memberUuid);
    SupabaseConnection::asAuthenticatedRole();

    $affected = $this->connection->executeStatement(
        'UPDATE kals SET name = :name WHERE id = :id',
        ['name' => 'Hijacked', 'id' => $this->fixture->kalId],
    );

    expect($affected)->toBe(0)
        ->and($this->connection->fetchOne('SELECT name FROM kals WHERE id = :id', ['id' => $this->fixture->kalId]))
        ->not->toBe('Hijacked');
});

it('lets a member read the kal locales and files', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->memberUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect($this->connection->fetchOne('SELECT count(*) FROM kal_locales'))->toBe(2)
        ->and($this->connection->fetchOne('SELECT count(*) FROM kal_files'))->toBe(1);
});

it('hides the kal locales and files from a stranger', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->strangerUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect($this->connection->fetchOne('SELECT count(*) FROM kal_locales'))->toBe(0)
        ->and($this->connection->fetchOne('SELECT count(*) FROM kal_files'))->toBe(0);
});

it('does not let a member add a file to the kal', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->memberUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(fn () => $this->connection->insert('kal_files', [
        'upload_id' => UlidValue::generate()->value(),
        'kal_id' => $this->fixture->kalId,
        'file_name' => 'sneaky.pdf',
        'file_path' => 'kal-patterns/sneaky.pdf',
        'file_size' => 1024,
        'file_extension' => 'pdf',
        'locale' => 'ca',
    ]))->toThrow(DriverException::class, 'row-level security policy');
});
