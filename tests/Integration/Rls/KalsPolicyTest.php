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

// El cas fort de la decisió del 2026-08-29: ni tan sols l'organitzadora pot
// crear el seu propi KAL des del client. És qui abans SÍ que podia (hi havia
// `kals_insert_organizer` i el grant), o sigui que és el test que se n'adona si
// algú els torna a posar.
it('refuses a kal created from the client, even by the organizer', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->organizerUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(fn () => $this->connection->insert('kals', [
        'id' => UlidValue::generate()->value(),
        'organizer_id' => $this->fixture->organizerId,
        'name' => 'Mine',
        'starts_on' => '2026-09-01 00:00:00',
        'invite_token' => UlidValue::generate()->value(),
    ]))->toThrow(DriverException::class, 'permission denied for table kals');
});

// Sense el grant d'UPDATE, Postgres talla ABANS d'avaluar la RLS: un UPDATE
// denegat ja no torna 0 files, llença. Per això aquí s'espera una excepció i no
// s'assereix el valor de la fila — l'error avorta la transacció del test i
// qualsevol SELECT posterior petaria amb `current transaction is aborted`.
it('refuses a kal renamed from the client, even by the organizer', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->organizerUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(fn () => $this->connection->executeStatement(
        'UPDATE kals SET name = :name WHERE id = :id',
        ['name' => 'Renamed', 'id' => $this->fixture->kalId],
    ))->toThrow(DriverException::class, 'permission denied for table kals');
});

it('does not let a member rename the kal', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->memberUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(fn () => $this->connection->executeStatement(
        'UPDATE kals SET name = :name WHERE id = :id',
        ['name' => 'Hijacked', 'id' => $this->fixture->kalId],
    ))->toThrow(DriverException::class, 'permission denied for table kals');
});

// Aquest test NO prova cap política: sobre `kals` ja no n'hi ha cap d'INSERT.
// Prova que la porta segueix tancada pel grant, i és exactament per això que hi
// és. Abans hi havia `kals_insert_organizer` amb un `with check` que comparava
// `organizer_id` amb qui trucava; ara no hi ha ni grant ni policy, o sigui que
// si algú tornés a concedir INSERT sobre `kals` NO quedaria cap `with check`
// que impedís crear un KAL a nom d'una altra persona — es reobriria alhora el
// forat del grant i el de la suplantació, i aquest test és l'únic lloc que ho
// cantaria. Es va esborrar el 2026-08-29 per duplicat (raonament correcte amb
// la informació d'aleshores) i es recupera com a guarda de regressió.
it('refuses a kal created on behalf of someone else', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->strangerUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(fn () => $this->connection->insert('kals', [
        'id' => UlidValue::generate()->value(),
        'organizer_id' => $this->fixture->organizerId,
        'name' => 'Not mine',
        'starts_on' => '2026-09-01 00:00:00',
        'invite_token' => UlidValue::generate()->value(),
    ]))->toThrow(DriverException::class, 'permission denied for table kals');
});

// TRUNCATE saltava la RLS sencera i el grant venia per defecte, sense que
// ningú el decidís: una participant qualsevol podia buidar tot l'agregat.
it('refuses a truncate of the aggregate from the client', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->memberUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(fn () => $this->connection->executeStatement('TRUNCATE TABLE kals CASCADE'))
        ->toThrow(DriverException::class, 'permission denied for table kals');
});

// El mateix però com a `anon`, que és un rol DIFERENT amb els seus propis
// grants: la revocació del 2026-08-29 només nomenava `authenticated`, així que
// una visitant sense loguejar va conservar el TRUNCATE sobre tot l'agregat un
// dia més. Provar només `authenticated` és el que va deixar passar aquell cas.
it('refuses a truncate of the aggregate from an anonymous caller', function (): void {
    SupabaseConnection::asAnonRole();

    expect(fn () => $this->connection->executeStatement('TRUNCATE TABLE kals CASCADE'))
        ->toThrow(DriverException::class, 'permission denied for table kals');
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
    ]))->toThrow(DriverException::class, 'permission denied for table kal_files');
});

// Les filles directes tenien `*_insert_organizer` i grant d'INSERT: eren la via
// per adjuntar el PDF del patró o habilitar un idioma des del client.
it('refuses a file added from the client, even by the organizer', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->organizerUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(fn () => $this->connection->insert('kal_files', [
        'upload_id' => UlidValue::generate()->value(),
        'kal_id' => $this->fixture->kalId,
        'file_name' => 'pattern.pdf',
        'file_path' => 'kal-patterns/pattern.pdf',
        'file_size' => 1024,
        'file_extension' => 'pdf',
        'locale' => 'ca',
    ]))->toThrow(DriverException::class, 'permission denied for table kal_files');
});

it('refuses a locale added from the client, even by the organizer', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->organizerUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(fn () => $this->connection->insert('kal_locales', [
        'kal_id' => $this->fixture->kalId,
        'locale' => 'en',
    ]))->toThrow(DriverException::class, 'permission denied for table kal_locales');
});
