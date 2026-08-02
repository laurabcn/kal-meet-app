<?php

declare(strict_types=1);

use App\Kal\Domain\Exception\KalException;
use App\Kal\Infrastructure\Persistence\DbalKalRepository;
use App\Shared\Domain\ValueObject\UlidValue;
use Psr\Log\NullLogger;
use Tests\Kal\Domain\Mother\ClueMother;
use Tests\Kal\Domain\Mother\CluesMother;
use Tests\Kal\Domain\Mother\FileMother;
use Tests\Kal\Domain\Mother\FilesMother;
use Tests\Kal\Domain\Mother\KalMother;
use Tests\Kal\Domain\Mother\MeetingMother;
use Tests\Kal\Domain\Mother\MeetingsMother;
use Tests\Kal\Infrastructure\Persistence\SupabaseConnection;

// Els únics tests que toquen Postgres. Van contra l'esquema REAL que
// supabase/migrations/ aplica a la Supabase local: és tot el sentit d'existir.
// Un doble en memòria o un SQLite amb les taules escrites a mà no poden dir-te
// si els ~40 noms de columna del repositori concorden amb les migracions, ni si
// l'ordre dels INSERT respecta les FKs, ni si el rollback desfà de veritat.

beforeEach(function (): void {
    SupabaseConnection::begin();

    $this->connection = SupabaseConnection::get();
    $this->repository = new DbalKalRepository($this->connection, new NullLogger());

    // La FK kals.organizer_id -> profiles.id demana un perfil de veritat.
    $this->organizerId = UlidValue::generate();
    SupabaseConnection::insertProfile($this->organizerId->value());
});

// Res del que facin els tests sobreviu: la transacció es desfà sempre, o sigui
// que no cal netejar taules en cap ordre concret.
afterEach(function (): void {
    SupabaseConnection::rollBack();
});

it('writes the whole aggregate across its six tables', function (): void {
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
        ->and($count('SELECT count(*) FROM meetings WHERE kal_id = :id'))->toBe(2)
        // La invariant que abans "provava" un comptador del doble en memòria.
        ->and($count('SELECT count(*) FROM debate_rooms WHERE kal_id = :id'))->toBe(1);
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
    // Si hi corre, l'emmascara: en petar l'INSERT, el `safeRollBack()` del
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

        expect(fn () => $this->repository->create($kal))
            ->toThrow(KalException::class, 'kal_persistence_failed');

        $id = $kal->id->value();
        $count = fn (string $sql): int => (int) $this->connection->fetchOne($sql, ['id' => $id]);

        expect($count('SELECT count(*) FROM kals WHERE id = :id'))->toBe(0)
            ->and($count('SELECT count(*) FROM kal_locales WHERE kal_id = :id'))->toBe(0)
            ->and($count('SELECT count(*) FROM clues WHERE kal_id = :id'))->toBe(0)
            ->and($count('SELECT count(*) FROM debate_rooms WHERE kal_id = :id'))->toBe(0);
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
        ->toThrow(KalException::class, 'kal_persistence_failed');
});
