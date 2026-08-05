<?php

declare(strict_types=1);

use App\Shared\Domain\ValueObject\UlidValue;
use Doctrine\DBAL\Exception\DriverException;
use Tests\Integration\Kal\Infrastructure\Persistence\SupabaseConnection;
use Tests\Integration\Rls\RlsFixture;

// La regla de producte més forta de l'esquema: abans de `starts_on`, el
// contingut d'una pista NO és visible per a les participants. CLAUDE.md diu que
// ha d'estar reforçada a la RLS i no només a la UI, i fins ara no hi havia cap
// test que ho comprovés — ni de la política ni de `is_clue_released`.

beforeEach(function (): void {
    SupabaseConnection::begin();

    $this->connection = SupabaseConnection::get();
    $this->fixture = RlsFixture::create($this->connection);

    $this->visibleClues = fn (): array => $this->connection->fetchFirstColumn('SELECT id FROM clues ORDER BY starts_on');
});

afterEach(function (): void {
    SupabaseConnection::rollBack();
});

it('reports a clue whose starts_on has passed as released', function (): void {
    $released = $this->connection->fetchOne(
        'SELECT is_clue_released(:id)',
        ['id' => $this->fixture->releasedClueId],
    );

    expect((bool) $released)->toBeTrue();
});

it('reports a clue whose starts_on is in the future as not released', function (): void {
    $released = $this->connection->fetchOne(
        'SELECT is_clue_released(:id)',
        ['id' => $this->fixture->unreleasedClueId],
    );

    expect((bool) $released)->toBeFalse();
});

it('reports an unknown clue as not released', function (): void {
    $released = $this->connection->fetchOne(
        'SELECT is_clue_released(:id)',
        ['id' => UlidValue::generate()->value()],
    );

    expect((bool) $released)->toBeFalse();
});

it('shows a member only the released clue', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->memberUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(($this->visibleClues)())->toBe([$this->fixture->releasedClueId]);
});

// L'organitzadora prepara les pistes abans de publicar-les: les ha de veure
// totes. Ho aconsegueix `clues_select_organizer`, que conviu amb la de membre
// (les polítiques permissives se sumen).
it('shows the organizer every clue, released or not', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->organizerUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(($this->visibleClues)())->toBe([
        $this->fixture->releasedClueId,
        $this->fixture->unreleasedClueId,
    ]);
});

it('hides every clue from a stranger', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->strangerUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(($this->visibleClues)())->toBeEmpty();
});

// El PDF de la pista és el bé més sensible del producte (por a la pirateria de
// patrons): que la fila no es vegi és el que impedeix arribar al `file_path`.
it('hides the unreleased clue pattern path from a member', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->memberUuid);
    SupabaseConnection::asAuthenticatedRole();

    $path = $this->connection->fetchOne(
        'SELECT file_path FROM clues WHERE id = :id',
        ['id' => $this->fixture->unreleasedClueId],
    );

    expect($path)->toBeFalse();
});

it('hides every clue once the kal is soft-deleted', function (): void {
    $this->connection->executeStatement(
        'UPDATE kals SET deleted_at = now() WHERE id = :id',
        ['id' => $this->fixture->kalId],
    );

    SupabaseConnection::authenticateAs($this->fixture->organizerUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(($this->visibleClues)())->toBeEmpty();
});

it('does not let a member add a clue', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->memberUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(fn () => $this->connection->insert('clues', [
        'id' => UlidValue::generate()->value(),
        'kal_id' => $this->fixture->kalId,
        'name' => 'Sneaky round',
        'starts_on' => '2026-08-02 00:00:00',
        'ends_on' => '2026-08-09 00:00:00',
        'locale' => 'ca',
        'file_name' => 'sneaky.pdf',
        'file_path' => 'kal-patterns/sneaky.pdf',
        'file_size' => 1024,
        'file_extension' => 'pdf',
        'file_locale' => 'ca',
        'file_upload_id' => UlidValue::generate()->value(),
    ]))->toThrow(DriverException::class, 'row-level security policy');
});

// Sense això una participant podria avançar-se una pista canviant-ne la data.
it('does not let a member release a clue early', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->memberUuid);
    SupabaseConnection::asAuthenticatedRole();

    $affected = $this->connection->executeStatement(
        'UPDATE clues SET starts_on = now() WHERE id = :id',
        ['id' => $this->fixture->unreleasedClueId],
    );

    expect($affected)->toBe(0);

    SupabaseConnection::authenticateAs($this->fixture->organizerUuid);
    expect((bool) $this->connection->fetchOne(
        'SELECT is_clue_released(:id)',
        ['id' => $this->fixture->unreleasedClueId],
    ))->toBeFalse();
});

it('lets the organizer reschedule a clue', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->organizerUuid);
    SupabaseConnection::asAuthenticatedRole();

    $affected = $this->connection->executeStatement(
        'UPDATE clues SET name = :name WHERE id = :id',
        ['name' => 'Renamed round', 'id' => $this->fixture->unreleasedClueId],
    );

    expect($affected)->toBe(1);
});
