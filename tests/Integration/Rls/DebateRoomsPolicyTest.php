<?php

declare(strict_types=1);

use App\Shared\Domain\ValueObject\UlidValue;
use Doctrine\DBAL\Exception\DriverException;
use Tests\Integration\Kal\Infrastructure\Persistence\SupabaseConnection;
use Tests\Integration\Rls\RlsFixture;

// L'aula encara no la crea ningú (el xat és candidat MVP, pendent de les
// entrevistes), però la taula i la política ja hi són. Es proven ara perquè el
// dia que el xat es cablegi la RLS ja estigui verificada: el frontend hi
// escriurà directament amb supabase-js i és tot el que hi haurà entremig.
//
// L'aula és a nivell de KAL i mai per pista, o sigui que aquí NO hi ha regla
// d'alliberament: qui és membre la veu, i prou.

beforeEach(function (): void {
    SupabaseConnection::begin();

    $this->connection = SupabaseConnection::get();
    $this->fixture = RlsFixture::create($this->connection);

    $this->visibleRooms = fn (): array => $this->connection->fetchFirstColumn('SELECT id FROM debate_rooms');
});

afterEach(function (): void {
    SupabaseConnection::rollBack();
});

it('lets a member read the debate room', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->memberUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(($this->visibleRooms)())->toBe([$this->fixture->debateRoomId]);
});

it('lets the organizer read the debate room', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->organizerUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(($this->visibleRooms)())->toBe([$this->fixture->debateRoomId]);
});

it('hides the debate room from a stranger', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->strangerUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(($this->visibleRooms)())->toBeEmpty();
});

it('hides the debate room once the kal is soft-deleted', function (): void {
    $this->connection->executeStatement(
        'UPDATE kals SET deleted_at = now() WHERE id = :id',
        ['id' => $this->fixture->kalId],
    );

    SupabaseConnection::authenticateAs($this->fixture->memberUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(($this->visibleRooms)())->toBeEmpty();
});

// No hi ha cap política d'INSERT a propòsit: l'aula la crearà el backend amb la
// service_role key, dins de la mateixa transacció que el KAL.
it('refuses a debate room created from the client', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->organizerUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(fn () => $this->connection->insert('debate_rooms', [
        'id' => UlidValue::generate()->value(),
        'kal_id' => $this->fixture->kalId,
    ]))->toThrow(DriverException::class, 'permission denied for table debate_rooms');
});
