<?php

declare(strict_types=1);

use App\Kal\Domain\Exception\KalAlreadyExistsException;
use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Exception\KalNotFoundException;
use App\Kal\Domain\InviteToken;
use App\Kal\Infrastructure\Repository\MySQL\Hydrator\KalHydrator;
use App\Kal\Infrastructure\Repository\MySQL\KalRepository;
use App\Shared\Domain\ValueObject\UlidValue;
use App\Shared\Infrastructure\Repository\MySQLRepository;
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

        // UniqueConstraintViolationException → kal_already_exists (amb rollback).
        expect(fn () => $this->repository->create($kal))
            ->toThrow(KalAlreadyExistsException::class, 'A kal with this id already exists.');

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
        ->toThrow(KalException::class, 'Failed to persist the kal.');
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
        ->toThrow(KalException::class, 'The kal is missing its debate room.');
});
