<?php

declare(strict_types=1);

use App\Kal\Domain\Exception\ClueNotFoundException;
use App\Kal\Domain\Exception\KalNotFoundException;
use App\Kal\Domain\Exception\KalStateException;
use App\Kal\Domain\InviteToken;
use App\Kal\Infrastructure\Repository\MySQL\Hydrator\KalHydrator;
use App\Kal\Infrastructure\Repository\MySQL\Hydrator\KalSummaryHydrator;
use App\Kal\Infrastructure\Repository\MySQL\KalRepository;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use App\Shared\Domain\ValueObject\UlidValue;
use App\Shared\Infrastructure\Repository\MySQLRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Log\NullLogger;
use Tests\Integration\Kal\Infrastructure\Persistence\SupabaseConnection;
use Tests\Unit\Kal\Domain\Mother\ClueMother;
use Tests\Unit\Kal\Domain\Mother\CluesMother;
use Tests\Unit\Kal\Domain\Mother\FileMother;
use Tests\Unit\Kal\Domain\Mother\FilesMother;
use Tests\Unit\Kal\Domain\Mother\KalMother;
use Tests\Unit\Kal\Domain\Mother\MeetingMother;
use Tests\Unit\Kal\Domain\Mother\MeetingsMother;

// Els únics tests que toquen Postgres. Van contra l'esquema REAL que
// supabase/migrations/ aplica a la Supabase local: és tot el sentit d'existir.
// Un doble en memòria o un SQLite amb les taules escrites a mà no poden dir-te
// si els ~40 noms de columna del repositori concorden amb les migracions, ni si
// l'ordre dels INSERT respecta les FKs, ni si el rollback desfà de veritat.

beforeEach(function (): void {
    SupabaseConnection::begin();

    $this->connection = SupabaseConnection::get();
    $this->repository = new KalRepository(
        new MySQLRepository($this->connection),
        new NullLogger(),
        new KalHydrator(),
        new KalSummaryHydrator(),
    );

    // La FK kals.organizer_id -> profiles.id demana un perfil de veritat.
    $this->organizerId = UlidValue::generate();
    SupabaseConnection::insertProfile($this->organizerId->value());
});

// Res del que facin els tests sobreviu: la transacció es desfà sempre, o sigui
// que no cal netejar taules en cap ordre concret.
afterEach(function (): void {
    SupabaseConnection::rollBack();
});

it('writes the whole aggregate across its tables', function (): void {
    $kal = KalMother::create(
        files: FilesMother::of(FileMother::create()),
        clues: CluesMother::of(ClueMother::create()),
        organizerId: $this->organizerId,
        meetings: MeetingsMother::of(MeetingMother::create()),
    );

    $this->repository->create($kal);

    $id = $kal->id->value();
    $count = fn (string $sql): int => (int) $this->connection->fetchOne($sql, ['id' => $id]);

    expect($count('SELECT count(*) FROM kals WHERE id = :id'))->toBe(1)
        ->and($count('SELECT count(*) FROM kal_locales WHERE kal_id = :id'))->toBe(2)
        ->and($count('SELECT count(*) FROM kal_files WHERE kal_id = :id'))->toBe(1)
        ->and($count('SELECT count(*) FROM clues WHERE kal_id = :id'))->toBe(1)
        // Dues: la del KAL i la obligatòria de la pista.
        ->and($count('SELECT count(*) FROM meetings WHERE kal_id = :id'))->toBe(2);
});

it('stores the kal columns with the values the domain holds', function (): void {
    // Que hi hagi una fila no vol dir que les columnes estiguin ben mapades:
    // un `starts_on` escrit a `ends_on` també compta com a fila.
    $kal = KalMother::create(organizerId: $this->organizerId);

    $this->repository->create($kal);

    $row = $this->connection->fetchAssociative('SELECT * FROM kals WHERE id = :id', ['id' => $kal->id->value()]);

    expect($row['organizer_id'])->toBe($this->organizerId->value())
        ->and($row['name'])->toBe($kal->name->value())
        ->and($row['invite_token'])->toBe($kal->inviteToken->value())
        ->and($row['deleted_at'])->toBeNull();
});

it('links a clue meeting to its clue and a kal meeting to none', function (): void {
    // L'ordre dels INSERT hi depèn: meetings.clue_id té FK cap a clues, o sigui
    // que la reunió de la pista s'ha d'escriure DESPRÉS de la pista.
    $kal = KalMother::create(
        clues: CluesMother::of(ClueMother::create()),
        organizerId: $this->organizerId,
        meetings: MeetingsMother::of(MeetingMother::create()),
    );

    $this->repository->create($kal);

    $clueId = $this->connection->fetchOne('SELECT id FROM clues WHERE kal_id = :id', ['id' => $kal->id->value()]);
    $linked = $this->connection->fetchFirstColumn(
        'SELECT clue_id FROM meetings WHERE kal_id = :id ORDER BY clue_id NULLS FIRST',
        ['id' => $kal->id->value()],
    );

    expect($linked)->toBe([null, $clueId]);
});

it('leaves nothing behind when a later insert fails', function (): void {
    // Aquest test NO pot córrer dins de la transacció d'aïllament dels altres.
    // Si hi corre, l'emmascara: en petar l'INSERT, el rollback del
    // repositori desfà la transacció DEL TEST i els comptadors donen 0 encara
    // que `create()` no n'hagi obert cap de pròpia. Comprovat mutant el
    // repositori: sense transacció, el test passava igual. Per això corre sense
    // xarxa de seguretat i neteja el que crea.
    SupabaseConnection::rollBack();

    $organizerId = UlidValue::generate();
    SupabaseConnection::insertProfile($organizerId->value());

    $kal = KalMother::create(
        clues: CluesMother::of(ClueMother::create()),
        organizerId: $organizerId,
        meetings: MeetingsMother::of(MeetingMother::create()),
    );

    // Un KAL esquer amb una reunió que ja ocupa l'id que el nostre voldrà:
    // l'INSERT de meetings petarà per clau primària duplicada.
    $decoyId = UlidValue::generate()->value();

    try {
        $this->connection->executeStatement(
            'INSERT INTO kals (id, organizer_id, name, starts_on, invite_token, created_at, updated_at)
             VALUES (:id, :organizer_id, :name, :starts_on, :invite_token, :created_at, :updated_at)',
            [
                'id' => $decoyId,
                'organizer_id' => $organizerId->value(),
                'name' => 'Decoy',
                'starts_on' => '2026-08-01 00:00:00',
                'invite_token' => UlidValue::generate()->value(),
                'created_at' => '2026-08-01 00:00:00',
                'updated_at' => '2026-08-01 00:00:00',
            ],
        );
        $this->connection->executeStatement(
            'INSERT INTO meetings (id, kal_id, title, url, scheduled_at, timezone)
             VALUES (:id, :kal_id, :title, :url, :scheduled_at, :timezone)',
            [
                'id' => $kal->meetings->all()[0]->id->value(),
                'kal_id' => $decoyId,
                'title' => 'Taken',
                'url' => 'https://example.com/taken',
                'scheduled_at' => '2026-08-01 00:00:00',
                'timezone' => 'Europe/Madrid',
            ],
        );

        // Unique d'una filla (meetings.id) → persistenceFailed, no kal_already_exists.
        expect(fn () => $this->repository->create($kal))
            ->toThrow(KalStateException::class, 'Failed to persist the kal.');

        $id = $kal->id->value();
        $count = fn (string $sql): int => (int) $this->connection->fetchOne($sql, ['id' => $id]);

        expect($count('SELECT count(*) FROM kals WHERE id = :id'))->toBe(0)
            ->and($count('SELECT count(*) FROM kal_locales WHERE kal_id = :id'))->toBe(0)
            ->and($count('SELECT count(*) FROM clues WHERE kal_id = :id'))->toBe(0);
    } finally {
        // Sense transacció que ho desfaci, i també si l'asserció ha petat: les
        // filles cauen soles per ON DELETE CASCADE des de kals.
        $this->connection->executeStatement('DELETE FROM kals WHERE id = :id', ['id' => $decoyId]);
        $this->connection->executeStatement('DELETE FROM kals WHERE id = :id', ['id' => $kal->id->value()]);
        $this->connection->executeStatement('DELETE FROM profiles WHERE id = :id', ['id' => $organizerId->value()]);
    }
});

it('reports a missing organizer as a persistence failure, not a crash', function (): void {
    // La FK cap a profiles: un organizer_id que no existeix és el cas real de
    // "el token porta un perfil que ja no hi és".
    $kal = KalMother::create(organizerId: UlidValue::generate());

    expect(fn () => $this->repository->create($kal))
        ->toThrow(KalStateException::class, 'Failed to persist the kal.');
});

it('throws kal_not_found when no kal exists for the given id', function (): void {
    expect(fn () => $this->repository->findById(UlidValue::generate(), $this->organizerId))
        ->toThrow(KalNotFoundException::class, 'Kal not found.');
});

it('throws kal_not_found when the kal is soft-deleted', function (): void {
    $kal = KalMother::create(organizerId: $this->organizerId);
    $this->repository->create($kal);

    $this->connection->executeStatement(
        'UPDATE kals SET deleted_at = now() WHERE id = :id',
        ['id' => $kal->id->value()],
    );

    expect(fn () => $this->repository->findById($kal->id, $this->organizerId))
        ->toThrow(KalNotFoundException::class, 'Kal not found.');
});

it('reconstructs the full aggregate graph, including the invite token', function (): void {
    $kal = KalMother::create(
        files: FilesMother::of(FileMother::create()),
        clues: CluesMother::of(ClueMother::create()),
        organizerId: $this->organizerId,
        meetings: MeetingsMother::of(MeetingMother::create()),
    );

    $this->repository->create($kal);

    $found = $this->repository->findById($kal->id, $this->organizerId);

    expect($found->id->equals($kal->id))->toBeTrue()
        ->and($found->organizerId->equals($kal->organizerId))->toBeTrue()
        ->and($found->name->value())->toBe($kal->name->value())
        ->and($found->inviteToken->equals($kal->inviteToken))->toBeTrue()
        ->and($found->inviteToken->value())->not->toBe('')
        ->and(array_map(fn ($locale) => $locale->value(), $found->locales->all()))
            ->toEqualCanonicalizing(array_map(fn ($locale) => $locale->value(), $kal->locales->all()))
        ->and($found->files->all())->toHaveCount(1)
        ->and($found->files->all()[0]->fileName->value())->toBe($kal->files->all()[0]->fileName->value())
        ->and($found->clues->all())->toHaveCount(1)
        ->and($found->clues->all()[0]->id->equals($kal->clues->all()[0]->id))->toBeTrue()
        ->and($found->clues->all()[0]->meeting->id->equals($kal->clues->all()[0]->meeting->id))->toBeTrue()
        ->and($found->clues->all()[0]->file->fileName->value())->toBe($kal->clues->all()[0]->file->fileName->value())
        // Una del KAL i una obligatòria de la pista: només la del KAL surt a `meetings`.
        ->and($found->meetings->all())->toHaveCount(1)
        ->and($found->meetings->all()[0]->id->equals($kal->meetings->all()[0]->id))->toBeTrue();
});

it('loads an active kal by id without requiring the organizer filter', function (): void {
    $kal = KalMother::create(organizerId: $this->organizerId);
    $this->repository->create($kal);

    $found = $this->repository->getActiveById($kal->id);

    expect($found->id->equals($kal->id))->toBeTrue()
        ->and($found->inviteToken->equals($kal->inviteToken))->toBeTrue();
});

it('throws kal_not_found from getActiveById when the kal is soft-deleted', function (): void {
    $kal = KalMother::create(organizerId: $this->organizerId);
    $this->repository->create($kal);

    $this->connection->executeStatement(
        'UPDATE kals SET deleted_at = now() WHERE id = :id',
        ['id' => $kal->id->value()],
    );

    expect(fn () => $this->repository->getActiveById($kal->id))
        ->toThrow(KalNotFoundException::class, 'Kal not found.');
});

// Qui pot apuntar-s'hi ho decideix `JoinPolicy`, no el repositori: aquí només
// es prova que el token obre el KAL correcte (veure JoinPolicyTest).
it('loads a kal by id when the invite token matches', function (): void {
    $kal = KalMother::create(organizerId: $this->organizerId);
    $this->repository->create($kal);

    $found = $this->repository->findByToken($kal->id, $kal->inviteToken);

    expect($found->id->equals($kal->id))->toBeTrue()
        ->and($found->inviteToken->equals($kal->inviteToken))->toBeTrue();
});

it('throws kal_not_found from findByToken when the invite token does not match', function (): void {
    $kal = KalMother::create(organizerId: $this->organizerId);
    $this->repository->create($kal);

    expect(fn () => $this->repository->findByToken(
        $kal->id,
        InviteToken::fromString('deadbeefdeadbeefdeadbeefdeadbeef'),
    ))->toThrow(KalNotFoundException::class, 'Kal not found.');
});

it('throws kal_not_found from findByToken when the kal is soft-deleted', function (): void {
    $kal = KalMother::create(organizerId: $this->organizerId);
    $this->repository->create($kal);

    $this->connection->executeStatement(
        'UPDATE kals SET deleted_at = now() WHERE id = :id',
        ['id' => $kal->id->value()],
    );

    expect(fn () => $this->repository->findByToken($kal->id, $kal->inviteToken))
        ->toThrow(KalNotFoundException::class, 'Kal not found.');
});

it('throws kal_not_found from findByToken when no kal exists for the given id', function (): void {
    expect(fn () => $this->repository->findByToken(
        UlidValue::generate(),
        InviteToken::fromString('deadbeefdeadbeefdeadbeefdeadbeef'),
    ))->toThrow(KalNotFoundException::class, 'Kal not found.');
});

// L'aula s'escriu dins de la MATEIXA transacció que el KAL: un KAL sense ella
// no es pot llegir, o sigui que no pot existir ni un instant.
it('writes the debate room in the same transaction as the kal', function (): void {
    $kal = KalMother::create(organizerId: $this->organizerId);

    $this->repository->create($kal);

    $row = $this->connection->fetchAssociative(
        'SELECT * FROM debate_rooms WHERE kal_id = :id',
        ['id' => $kal->id->value()],
    );

    expect($row['id'])->toBe($kal->debateRoom->id->value())
        ->and($row['created_at'])->not->toBeNull();
});

it('reconstructs the debate room when loading the kal', function (): void {
    $kal = KalMother::create(organizerId: $this->organizerId);
    $this->repository->create($kal);

    $found = $this->repository->getActiveById($kal->id);

    expect($found->debateRoom->id->equals($kal->debateRoom->id))->toBeTrue();
});

it('reports a kal whose debate room went missing as unreadable', function (): void {
    $kal = KalMother::create(organizerId: $this->organizerId);
    $this->repository->create($kal);
    $this->connection->executeStatement(
        'DELETE FROM debate_rooms WHERE kal_id = :id',
        ['id' => $kal->id->value()],
    );

    expect(fn () => $this->repository->getActiveById($kal->id))
        ->toThrow(KalStateException::class, 'The kal does not have exactly one debate room.');
});

it('updates scalar kal columns without touching child tables', function (): void {
    $kal = KalMother::create(
        files: FilesMother::of(FileMother::create()),
        clues: CluesMother::of(ClueMother::create()),
        organizerId: $this->organizerId,
        endsOn: DateTime::create('2026-09-01 00:00:00'),
        meetings: MeetingsMother::of(MeetingMother::create()),
    );
    $this->repository->create($kal);

    // Dates stay within the existing clue range; only scalars that don't
    // shrink the window need asserting here.
    $kal->update(
        NonEmptyStringValue::create('Updated name'),
        NonEmptyStringValue::create('Updated description'),
        DateTime::create('2026-07-15 00:00:00'),
        DateTime::create('2026-09-15 00:00:00'),
        'updated/cover.webp',
    );
    $this->repository->update($kal);

    $row = $this->connection->fetchAssociative('SELECT * FROM kals WHERE id = :id', ['id' => $kal->id->value()]);
    $id = $kal->id->value();
    $count = fn (string $sql): int => (int) $this->connection->fetchOne($sql, ['id' => $id]);

    expect($row['name'])->toBe('Updated name')
        ->and($row['description'])->toBe('Updated description')
        ->and($row['cover_path'])->toBe('updated/cover.webp')
        ->and($row['invite_token'])->toBe($kal->inviteToken->value())
        ->and($count('SELECT count(*) FROM kal_locales WHERE kal_id = :id'))->toBe(2)
        ->and($count('SELECT count(*) FROM kal_files WHERE kal_id = :id'))->toBe(1)
        ->and($count('SELECT count(*) FROM clues WHERE kal_id = :id'))->toBe(1)
        ->and($count('SELECT count(*) FROM meetings WHERE kal_id = :id'))->toBe(2)
        ->and($count('SELECT count(*) FROM debate_rooms WHERE kal_id = :id'))->toBe(1);

    $found = $this->repository->findById($kal->id, $this->organizerId);
    expect($found->name->value())->toBe('Updated name')
        ->and($found->description?->value())->toBe('Updated description')
        ->and($found->startsOn->value())->toBe('2026-07-15 00:00:00')
        ->and($found->endsOn?->value())->toBe('2026-09-15 00:00:00')
        ->and($found->coverPath)->toBe('updated/cover.webp')
        ->and($found->updatedAt->value())->toBe($kal->updatedAt->value());
});

it('throws kal_not_found when updating a soft-deleted kal', function (): void {
    $kal = KalMother::create(organizerId: $this->organizerId);
    $this->repository->create($kal);

    $this->connection->executeStatement(
        'UPDATE kals SET deleted_at = now() WHERE id = :id',
        ['id' => $kal->id->value()],
    );

    $kal->update(
        NonEmptyStringValue::create('Should fail'),
        $kal->description,
        $kal->startsOn,
        $kal->endsOn,
        $kal->coverPath,
    );

    expect(fn () => $this->repository->update($kal))
        ->toThrow(KalNotFoundException::class, 'Kal not found.');
});

it('marks deleted_at on the kal and cascades it to every child row', function (): void {
    $kal = KalMother::create(
        files: FilesMother::of(FileMother::create()),
        clues: CluesMother::of(ClueMother::create()),
        organizerId: $this->organizerId,
        meetings: MeetingsMother::of(MeetingMother::create()),
    );
    $this->repository->create($kal);

    $kal->delete();
    $this->repository->delete($kal);

    $id = $kal->id->value();
    $row = $this->connection->fetchAssociative('SELECT * FROM kals WHERE id = :id', ['id' => $id]);
    $alive = fn (string $table): int => (int) $this->connection->fetchOne(
        'SELECT count(*) FROM '.$table.' WHERE kal_id = :id AND deleted_at IS NULL',
        ['id' => $id],
    );
    $marked = fn (string $table): int => (int) $this->connection->fetchOne(
        'SELECT count(*) FROM '.$table.' WHERE kal_id = :id AND deleted_at IS NOT NULL',
        ['id' => $id],
    );

    // Cap fila desapareix: totes queden, totes marcades.
    expect($row)->not->toBeFalse()
        ->and($row['deleted_at'])->not->toBeNull()
        ->and($marked('kal_locales'))->toBe(2)
        ->and($marked('kal_files'))->toBe(1)
        ->and($marked('clues'))->toBe(1)
        ->and($marked('meetings'))->toBe(2)
        ->and($marked('debate_rooms'))->toBe(1)
        ->and($alive('kal_locales'))->toBe(0)
        ->and($alive('kal_files'))->toBe(0)
        ->and($alive('clues'))->toBe(0)
        ->and($alive('meetings'))->toBe(0)
        ->and($alive('debate_rooms'))->toBe(0);

    expect(fn () => $this->repository->findById($kal->id, $this->organizerId))
        ->toThrow(KalNotFoundException::class, 'Kal not found.');
    expect(fn () => $this->repository->getActiveById($kal->id))
        ->toThrow(KalNotFoundException::class, 'Kal not found.');
});

it('leaves the children of another kal untouched', function (): void {
    $victim = KalMother::create(
        clues: CluesMother::of(ClueMother::create()),
        organizerId: $this->organizerId,
        meetings: MeetingsMother::of(MeetingMother::create()),
    );
    $survivor = KalMother::create(
        clues: CluesMother::of(ClueMother::create()),
        organizerId: $this->organizerId,
        meetings: MeetingsMother::of(MeetingMother::create()),
    );
    $this->repository->create($victim);
    $this->repository->create($survivor);

    $victim->delete();
    $this->repository->delete($victim);

    $survivorId = $survivor->id->value();
    $alive = fn (string $table): int => (int) $this->connection->fetchOne(
        'SELECT count(*) FROM '.$table.' WHERE kal_id = :id AND deleted_at IS NULL',
        ['id' => $survivorId],
    );

    expect($alive('kal_locales'))->toBe(2)
        ->and($alive('clues'))->toBe(1)
        ->and($alive('meetings'))->toBe(2)
        ->and($alive('debate_rooms'))->toBe(1)
        ->and($this->repository->findById($survivor->id, $this->organizerId)->clues->all())->toHaveCount(1);
});

it('does not mark any child when the caller is not the organizer', function (): void {
    $kal = KalMother::create(
        clues: CluesMother::of(ClueMother::create()),
        organizerId: $this->organizerId,
        meetings: MeetingsMother::of(MeetingMother::create()),
    );
    $this->repository->create($kal);

    $otherOrganizerId = UlidValue::generate();
    SupabaseConnection::insertProfile($otherOrganizerId->value());

    // Mateix id, una altra organitzadora: el `WHERE` del repositori l'ha de
    // deixar fora encara que l'agregat vingui marcat.
    $impostor = KalMother::create(id: $kal->id, organizerId: $otherOrganizerId);
    $impostor->delete();

    expect(fn () => $this->repository->delete($impostor))
        ->toThrow(KalNotFoundException::class, 'Kal not found.');

    $id = $kal->id->value();
    $marked = (int) $this->connection->fetchOne(
        'SELECT count(*) FROM clues WHERE kal_id = :id AND deleted_at IS NOT NULL',
        ['id' => $id],
    );
    expect($marked)->toBe(0);
});

it('throws kal_not_found when deleting a kal of another organizer', function (): void {
    $kal = KalMother::create(organizerId: $this->organizerId);
    $this->repository->create($kal);

    $otherOrganizerId = UlidValue::generate();
    SupabaseConnection::insertProfile($otherOrganizerId->value());

    // Mateix id, una altra organitzadora: el `WHERE` del repositori l'ha de
    // deixar fora encara que l'agregat vingui marcat.
    $impostor = KalMother::create(id: $kal->id, organizerId: $otherOrganizerId);
    $impostor->delete();

    expect(fn () => $this->repository->delete($impostor))
        ->toThrow(KalNotFoundException::class, 'Kal not found.');

    $deletedAt = $this->connection->fetchOne(
        'SELECT deleted_at FROM kals WHERE id = :id',
        ['id' => $kal->id->value()],
    );
    expect($deletedAt)->toBeNull();
});

it('lists only the active kals of the organizer, latest start first', function (): void {
    $older = KalMother::create(
        organizerId: $this->organizerId,
        startsOn: DateTime::create('2026-01-01 00:00:00'),
        endsOn: DateTime::create('2026-02-01 00:00:00'),
    );
    $newer = KalMother::create(
        organizerId: $this->organizerId,
        name: NonEmptyStringValue::create('El més nou'),
        startsOn: DateTime::create('2026-09-01 00:00:00'),
        endsOn: DateTime::create('2026-10-01 00:00:00'),
    );
    $deleted = KalMother::create(organizerId: $this->organizerId);
    $this->repository->create($older);
    $this->repository->create($newer);
    $this->repository->create($deleted);
    $deleted->delete();
    $this->repository->delete($deleted);

    $otherOrganizerId = UlidValue::generate();
    SupabaseConnection::insertProfile($otherOrganizerId->value());
    $this->repository->create(KalMother::create(organizerId: $otherOrganizerId));

    $summaries = $this->repository->findAllByOrganizer($this->organizerId);

    expect(array_map(static fn ($summary): string => $summary->id->value(), $summaries))
        ->toBe([$newer->id->value(), $older->id->value()])
        ->and($summaries[0]->name->value())->toBe('El més nou')
        ->and($summaries[0]->startsOn->value())->toBe('2026-09-01 00:00:00')
        ->and($summaries[0]->endsOn?->value())->toBe('2026-10-01 00:00:00');
});

it('returns an empty list for an organizer without kals', function (): void {
    expect($this->repository->findAllByOrganizer($this->organizerId))->toBe([]);
});

it('writes a clue and its meeting, linked, in that order', function (): void {
    $kal = KalMother::create(organizerId: $this->organizerId, endsOn: DateTime::create('2026-12-01 00:00:00'));
    $this->repository->create($kal);

    $clue = ClueMother::create(name: 'Pista 2');
    $kal->addClue($clue);
    $this->repository->addClue($kal->id, $clue);

    $row = $this->connection->fetchAssociative(
        'SELECT * FROM clues WHERE id = :id',
        ['id' => $clue->id->value()],
    );
    $meeting = $this->connection->fetchAssociative(
        'SELECT * FROM meetings WHERE clue_id = :id',
        ['id' => $clue->id->value()],
    );

    expect($row['name'])->toBe('Pista 2')
        ->and($row['kal_id'])->toBe($kal->id->value())
        ->and($row['deleted_at'])->toBeNull()
        ->and($meeting)->not->toBeFalse()
        ->and($meeting['id'])->toBe($clue->meeting->id->value());

    // I es torna a llegir dins de l'agregat.
    expect($this->repository->findById($kal->id, $this->organizerId)->clues->all())->toHaveCount(1);
});

it('refuses a second meeting for the same clue', function (): void {
    $kal = KalMother::create(organizerId: $this->organizerId, endsOn: DateTime::create('2026-12-01 00:00:00'));
    $this->repository->create($kal);
    $clue = ClueMother::create();
    $kal->addClue($clue);
    $this->repository->addClue($kal->id, $clue);

    // L'índex únic parcial de `meetings_clue_id_unique`: sense ell, l'hidratador
    // es quedaria en silenci amb una de les dues.
    expect(fn () => $this->connection->executeStatement(
        'INSERT INTO meetings (id, kal_id, clue_id, title, url, scheduled_at, timezone)
         VALUES (:id, :kal_id, :clue_id, :title, :url, :scheduled_at, :timezone)',
        [
            'id' => UlidValue::generate()->value(),
            'kal_id' => $kal->id->value(),
            'clue_id' => $clue->id->value(),
            'title' => 'Duplicada',
            'url' => 'https://example.com/duplicada',
            'scheduled_at' => '2026-08-02 00:00:00',
            'timezone' => 'Europe/Madrid',
        ],
    ))->toThrow(UniqueConstraintViolationException::class);
});

it('updates the scalar columns of a clue without touching its file or meeting', function (): void {
    $kal = KalMother::create(organizerId: $this->organizerId, endsOn: DateTime::create('2026-12-01 00:00:00'));
    $this->repository->create($kal);
    $clue = ClueMother::create(name: 'Abans');
    $kal->addClue($clue);
    $this->repository->addClue($kal->id, $clue);

    $updated = $kal->updateClue(
        $clue->id,
        NonEmptyStringValue::create('Després'),
        NonEmptyStringValue::create('Nova'),
        $clue->startsOn,
        $clue->endsOn,
        $clue->locale,
    );
    $this->repository->updateClue($kal->id, $updated);

    $row = $this->connection->fetchAssociative('SELECT * FROM clues WHERE id = :id', ['id' => $clue->id->value()]);

    expect($row['name'])->toBe('Després')
        ->and($row['description'])->toBe('Nova')
        ->and($row['file_upload_id'])->toBe($clue->file->uploadId->value())
        ->and((int) $this->connection->fetchOne(
            'SELECT count(*) FROM meetings WHERE clue_id = :id',
            ['id' => $clue->id->value()],
        ))->toBe(1);
});

it('marks the clue and its meeting when a clue is deleted', function (): void {
    $kal = KalMother::create(organizerId: $this->organizerId, endsOn: DateTime::create('2026-12-01 00:00:00'));
    $this->repository->create($kal);
    $kept = ClueMother::create(name: 'Es queda');
    $gone = ClueMother::create(name: 'Sen va');
    $kal->addClue($kept);
    $kal->addClue($gone);
    $this->repository->addClue($kal->id, $kept);
    $this->repository->addClue($kal->id, $gone);

    $removed = $kal->removeClue($gone->id);
    $this->repository->deleteClue($kal->id, $removed);

    $clueDeletedAt = $this->connection->fetchOne(
        'SELECT deleted_at FROM clues WHERE id = :id',
        ['id' => $gone->id->value()],
    );
    $meetingDeletedAt = $this->connection->fetchOne(
        'SELECT deleted_at FROM meetings WHERE clue_id = :id',
        ['id' => $gone->id->value()],
    );

    expect($clueDeletedAt)->not->toBeNull()
        ->and($meetingDeletedAt)->not->toBeNull();

    // La pista que es queda segueix sencera, i és l'única que es llegeix.
    $reloaded = $this->repository->findById($kal->id, $this->organizerId);
    expect($reloaded->clues->all())->toHaveCount(1)
        ->and($reloaded->clues->all()[0]->name->value())->toBe('Es queda')
        ->and($reloaded->clues->all()[0]->meeting->id->equals($kept->meeting->id))->toBeTrue();
});

it('throws clue_not_found when deleting a clue twice', function (): void {
    $kal = KalMother::create(organizerId: $this->organizerId, endsOn: DateTime::create('2026-12-01 00:00:00'));
    $this->repository->create($kal);
    $clue = ClueMother::create();
    $kal->addClue($clue);
    $this->repository->addClue($kal->id, $clue);

    $this->repository->deleteClue($kal->id, $clue);

    expect(fn () => $this->repository->deleteClue($kal->id, $clue))
        ->toThrow(ClueNotFoundException::class, 'Clue not found.');
});

it('reads the clues of a kal ordered by starts_on', function (): void {
    $kal = KalMother::create(organizerId: $this->organizerId, endsOn: DateTime::create('2026-12-01 00:00:00'));
    $this->repository->create($kal);

    // S'escriuen desordenades a propòsit.
    $third = ClueMother::create(
        name: 'Tercera',
        startsOn: DateTime::create('2026-08-20 00:00:00'),
        endsOn: DateTime::create('2026-08-27 00:00:00'),
    );
    $first = ClueMother::create(
        name: 'Primera',
        startsOn: DateTime::create('2026-08-01 00:00:00'),
        endsOn: DateTime::create('2026-08-08 00:00:00'),
    );
    $second = ClueMother::create(
        name: 'Segona',
        startsOn: DateTime::create('2026-08-10 00:00:00'),
        endsOn: DateTime::create('2026-08-17 00:00:00'),
    );
    foreach ([$third, $first, $second] as $clue) {
        $kal->addClue($clue);
        $this->repository->addClue($kal->id, $clue);
    }

    $names = array_map(
        static fn ($clue): string => $clue->name->value(),
        $this->repository->findById($kal->id, $this->organizerId)->clues->all(),
    );

    expect($names)->toBe(['Primera', 'Segona', 'Tercera']);
});

it('throws kal_not_found when deleting an already deleted kal', function (): void {
    $kal = KalMother::create(organizerId: $this->organizerId);
    $this->repository->create($kal);

    $kal->delete();
    $this->repository->delete($kal);

    expect(fn () => $this->repository->delete($kal))
        ->toThrow(KalNotFoundException::class, 'Kal not found.');
});
