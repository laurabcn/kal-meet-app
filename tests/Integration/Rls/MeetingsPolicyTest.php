<?php

declare(strict_types=1);

use App\Shared\Domain\ValueObject\UlidValue;
use Doctrine\DBAL\Exception\DriverException;
use Tests\Integration\Kal\Infrastructure\Persistence\SupabaseConnection;
use Tests\Integration\Rls\RlsFixture;

// `meetings` és l'única taula on l'alliberament de pista es barreja amb un cas
// que no en depèn: les reunions de KAL (`clue_id is null`) es veuen sempre, i
// les de pista només quan la pista ja ha sortit. Un `is_clue_released(clue_id)`
// sense el `clue_id is null or ...` amagaria totes les reunions de KAL, perquè
// la funció retorna false per a un id nul.

beforeEach(function (): void {
    SupabaseConnection::begin();

    $this->connection = SupabaseConnection::get();
    $this->fixture = RlsFixture::create($this->connection);

    $this->visibleMeetings = fn (): array => $this->connection->fetchFirstColumn(
        'SELECT id FROM meetings ORDER BY id',
    );
    $this->sorted = static function (array $ids): array {
        sort($ids);

        return $ids;
    };
});

afterEach(function (): void {
    SupabaseConnection::rollBack();
});

it('shows a member the kal meeting and the released clue meeting only', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->memberUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(($this->visibleMeetings)())->toBe(($this->sorted)([
        $this->fixture->kalMeetingId,
        $this->fixture->releasedClueMeetingId,
    ]));
});

// El cas que la regla ha de protegir: la reunió d'una pista futura filtraria
// quan comença, que és mitja sorpresa d'un MKAL.
it('hides the meeting of an unreleased clue from a member', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->memberUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(($this->visibleMeetings)())->not->toContain($this->fixture->unreleasedClueMeetingId);
});

it('shows the organizer every meeting', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->organizerUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(($this->visibleMeetings)())->toBe(($this->sorted)([
        $this->fixture->kalMeetingId,
        $this->fixture->releasedClueMeetingId,
        $this->fixture->unreleasedClueMeetingId,
    ]));
});

it('hides every meeting from a stranger', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->strangerUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(($this->visibleMeetings)())->toBeEmpty();
});

it('hides every meeting once the kal is soft-deleted', function (): void {
    $this->connection->executeStatement(
        'UPDATE kals SET deleted_at = now() WHERE id = :id',
        ['id' => $this->fixture->kalId],
    );

    SupabaseConnection::authenticateAs($this->fixture->memberUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(($this->visibleMeetings)())->toBeEmpty();
});

it('does not let a member schedule a meeting', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->memberUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(fn () => $this->connection->insert('meetings', [
        'id' => UlidValue::generate()->value(),
        'kal_id' => $this->fixture->kalId,
        'title' => 'Sneaky call',
        'url' => 'https://zoom.us/j/000',
        'scheduled_at' => '2026-08-20 18:00:00',
        'timezone' => 'Europe/Madrid',
    ]))->toThrow(DriverException::class, 'permission denied for table meetings');
});

it('refuses a meeting scheduled from the client, even by the organizer', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->organizerUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(fn () => $this->connection->insert('meetings', [
        'id' => UlidValue::generate()->value(),
        'kal_id' => $this->fixture->kalId,
        'title' => 'Kickoff call',
        'url' => 'https://zoom.us/j/111',
        'scheduled_at' => '2026-08-20 18:00:00',
        'timezone' => 'Europe/Madrid',
    ]))->toThrow(DriverException::class, 'permission denied for table meetings');
});

it('does not let a member move a meeting', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->memberUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(fn () => $this->connection->executeStatement(
        'UPDATE meetings SET title = :title WHERE id = :id',
        ['title' => 'Hijacked', 'id' => $this->fixture->kalMeetingId],
    ))->toThrow(DriverException::class, 'permission denied for table meetings');
});

it('refuses a meeting moved from the client, even by the organizer', function (): void {
    SupabaseConnection::authenticateAs($this->fixture->organizerUuid);
    SupabaseConnection::asAuthenticatedRole();

    expect(fn () => $this->connection->executeStatement(
        'UPDATE meetings SET title = :title WHERE id = :id',
        ['title' => 'Moved', 'id' => $this->fixture->kalMeetingId],
    ))->toThrow(DriverException::class, 'permission denied for table meetings');
});
