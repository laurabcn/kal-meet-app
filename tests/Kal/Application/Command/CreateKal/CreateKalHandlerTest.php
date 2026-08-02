<?php

declare(strict_types=1);

use App\Kal\Application\Command\CreateKal\CreateKalCommand;
use App\Kal\Application\Command\CreateKal\CreateKalHandler;
use App\Kal\Domain\Exception\KalException;
use App\Shared\Domain\Exception\InvalidArgumentException;
use Tests\Kal\Infrastructure\Persistence\InMemoryKalRepository;

beforeEach(function (): void {
    $this->repository = new InMemoryKalRepository();
    $this->handler = new CreateKalHandler($this->repository);
});

it('creates a kal with minimum required fields and persists it', function (): void {
    $command = new CreateKalCommand(
        id: '01J5M6XQBR4GTYHN8KZXP0F1W3',
        organizerId: '01J5M6XQBR4GTYHN8KZXP0F1W2',
        name: 'Summer Shawl KAL',
        startsOn: '2026-08-01 00:00:00',
        locales: ['ca', 'es'],
    );

    ($this->handler)($command);

    $kals = $this->repository->all();
    expect($kals)->toHaveCount(1);

    $kal = $kals[0];
    expect($kal->id->value())->toBe('01J5M6XQBR4GTYHN8KZXP0F1W3')
        ->and($kal->organizerId->value())->toBe('01J5M6XQBR4GTYHN8KZXP0F1W2')
        ->and($kal->name->value())->toBe('Summer Shawl KAL')
        ->and($kal->startsOn->value())->toBe('2026-08-01 00:00:00')
        ->and($kal->locales->all())->toHaveCount(2)
        ->and($kal->description)->toBeNull()
        ->and($kal->endsOn)->toBeNull()
        ->and($kal->coverPath)->toBeNull()
        ->and($kal->inviteToken->value())->not->toBeEmpty()
        ->and($kal->meetings->all())->toBeEmpty()
        ->and($kal->files->all())->toBeEmpty()
        ->and($kal->clues->all())->toBeEmpty();
});

// Aquí hi havia "it creates exactly one debate room when persisting", que
// comprovava un comptador del doble en memòria incrementat a cada `create()`.
// L'aula de debat la crea `DbalKalRepository::insertDebateRoom()` en SQL i el
// handler no hi decideix res, o sigui que aquell test només podia fallar si no
// es cridava `create()` — cosa que la resta de tests ja cobreix. Eliminat: la
// invariant "una aula per KAL" necessita un test contra BD de veritat, que
// encara no existeix.

it('lets a persistence failure surface instead of reporting success', function (): void {
    // El camí que el doble amagava mentre no sabia fallar: si la BD peta a mig
    // `create()`, el handler no ho ha de convertir en un final feliç.
    $this->repository->failWith(KalException::persistenceFailed(new RuntimeException('connection lost')));

    $command = new CreateKalCommand(
        id: '01J5M6XQBR4GTYHN8KZXP0F1W3',
        organizerId: '01J5M6XQBR4GTYHN8KZXP0F1W2',
        name: 'Doomed KAL',
        startsOn: '2026-08-01 00:00:00',
        locales: ['ca'],
    );

    expect(fn () => ($this->handler)($command))
        ->toThrow(KalException::class, 'kal_persistence_failed');
});

it('creates a kal with all optional fields', function (): void {
    $command = new CreateKalCommand(
        id: '01J5M6XQBR4GTYHN8KZXP0F1W3',
        organizerId: '01J5M6XQBR4GTYHN8KZXP0F1W2',
        name: 'Full KAL',
        startsOn: '2026-08-01 00:00:00',
        locales: ['ca', 'es'],
        description: 'A complete KAL with all the trimmings',
        endsOn: '2026-09-01 00:00:00',
        coverPath: 'kals/full-kal/portada.webp',
    );

    ($this->handler)($command);

    $kal = $this->repository->all()[0];
    expect($kal->description?->value())->toBe('A complete KAL with all the trimmings')
        ->and($kal->endsOn?->value())->toBe('2026-09-01 00:00:00')
        ->and($kal->coverPath)->toBe('kals/full-kal/portada.webp');
});

it('creates a kal with meetings', function (): void {
    $command = new CreateKalCommand(
        id: '01J5M6XQBR4GTYHN8KZXP0F1W3',
        organizerId: '01J5M6XQBR4GTYHN8KZXP0F1W2',
        name: 'Meeting KAL',
        startsOn: '2026-08-01 00:00:00',
        locales: ['ca'],
        meetings: [
            [
                'scheduledAt' => '2026-08-15 18:00:00',
                'url' => 'https://zoom.us/j/123456789',
                'title' => 'Weekly catch-up',
                'timezone' => 'Europe/Madrid',
            ],
            [
                'scheduledAt' => '2026-08-22 18:00:00',
                'url' => 'https://meet.google.com/abc-defg-hij',
                'title' => 'Second session',
                'timezone' => null,
            ],
        ],
    );

    ($this->handler)($command);

    $kal = $this->repository->all()[0];
    expect($kal->meetings->all())->toHaveCount(2)
        ->and($kal->meetings->all()[0]->title->value())->toBe('Weekly catch-up')
        ->and($kal->meetings->all()[0]->timezone)->toBe('Europe/Madrid')
        ->and($kal->meetings->all()[1]->title->value())->toBe('Second session')
        ->and($kal->meetings->all()[1]->timezone)->toBe('Europe/Madrid');
});

it('parses meeting scheduled at in the meeting timezone', function (): void {
    // 18:00 in America/New_York = 22:00 UTC (EDT offset = -4h)
    $command = new CreateKalCommand(
        id: '01J5M6XQBR4GTYHN8KZXP0F1W3',
        organizerId: '01J5M6XQBR4GTYHN8KZXP0F1W2',
        name: 'Timezone KAL',
        startsOn: '2026-08-01 00:00:00',
        locales: ['ca'],
        meetings: [
            [
                'scheduledAt' => '2026-08-15 18:00:00',
                'url' => 'https://zoom.us/j/111',
                'title' => 'NYC session',
                'timezone' => 'America/New_York',
            ],
        ],
    );

    ($this->handler)($command);

    $meeting = $this->repository->all()[0]->meetings->all()[0];
    // DateTime::value() returns UTC-formatted string
    expect($meeting->scheduledAt->value())->toBe('2026-08-15 22:00:00')
        ->and($meeting->timezone)->toBe('America/New_York');
});

it('parses meeting scheduled at with default timezone when omitted', function (): void {
    // 18:00 in Europe/Madrid = 16:00 UTC (CEST offset = +2h)
    $command = new CreateKalCommand(
        id: '01J5M6XQBR4GTYHN8KZXP0F1W3',
        organizerId: '01J5M6XQBR4GTYHN8KZXP0F1W2',
        name: 'Default TZ KAL',
        startsOn: '2026-08-01 00:00:00',
        locales: ['ca'],
        meetings: [
            [
                'scheduledAt' => '2026-08-15 18:00:00',
                'url' => 'https://zoom.us/j/222',
                'title' => 'Madrid session',
                'timezone' => null,
            ],
        ],
    );

    ($this->handler)($command);

    $meeting = $this->repository->all()[0]->meetings->all()[0];
    expect($meeting->scheduledAt->value())->toBe('2026-08-15 16:00:00')
        ->and($meeting->timezone)->toBe('Europe/Madrid');
});

it('deduplicates locales without causing persistence errors', function (): void {
    $command = new CreateKalCommand(
        id: '01J5M6XQBR4GTYHN8KZXP0F1W3',
        organizerId: '01J5M6XQBR4GTYHN8KZXP0F1W2',
        name: 'Duplicate Locales KAL',
        startsOn: '2026-08-01 00:00:00',
        locales: ['ca', 'CA', 'es', 'ca'],
    );

    ($this->handler)($command);

    $kal = $this->repository->all()[0];
    expect($kal->locales->all())->toHaveCount(2);
});

it('creates a kal with files', function (): void {
    $command = new CreateKalCommand(
        id: '01J5M6XQBR4GTYHN8KZXP0F1W3',
        organizerId: '01J5M6XQBR4GTYHN8KZXP0F1W2',
        name: 'File KAL',
        startsOn: '2026-08-01 00:00:00',
        locales: ['ca'],
        files: [
            [
                'fileName' => 'pattern-ca.pdf',
                'filePath' => 'kals/file-kal/pattern-ca.pdf',
                'fileSize' => 2048,
                'fileExtension' => 'pdf',
                'locale' => 'ca',
                'uploadId' => '01J5M6XQBR4GTYHN8KZXP0F1W3',
                'uploadedAt' => '2026-07-25 10:00:00',
            ],
        ],
    );

    ($this->handler)($command);

    $kal = $this->repository->all()[0];
    expect($kal->files->all())->toHaveCount(1)
        ->and($kal->files->all()[0]->fileName->value())->toBe('pattern-ca.pdf');
});

it('creates a kal with clues', function (): void {
    $command = new CreateKalCommand(
        id: '01J5M6XQBR4GTYHN8KZXP0F1W3',
        organizerId: '01J5M6XQBR4GTYHN8KZXP0F1W2',
        name: 'Clue KAL',
        startsOn: '2026-08-01 00:00:00',
        locales: ['ca'],
        clues: [
            [
                'name' => 'Round 1',
                'startsOn' => '2026-08-01 00:00:00',
                'endsOn' => '2026-08-08 00:00:00',
                'description' => 'First round of the shawl',
                'locale' => 'ca',
                'file' => [
                    'fileName' => 'round-1.pdf',
                    'filePath' => 'kals/clue-kal/round-1.pdf',
                    'fileSize' => 1024,
                    'fileExtension' => 'pdf',
                    'locale' => 'ca',
                    'uploadId' => '01J5M6XQBR4GTYHN8KZXP0F1W4',
                    'uploadedAt' => '2026-07-25 10:00:00',
                ],
                'meeting' => [
                    'scheduledAt' => '2026-08-03 18:00:00',
                    'url' => 'https://zoom.us/j/round-1',
                    'title' => 'Round 1 live session',
                    'timezone' => 'Europe/Madrid',
                ],
            ],
        ],
        endsOn: '2026-09-01 00:00:00',
    );

    ($this->handler)($command);

    $kal = $this->repository->all()[0];
    expect($kal->clues->all())->toHaveCount(1)
        ->and($kal->clues->all()[0]->name->value())->toBe('Round 1')
        ->and($kal->clues->all()[0]->description?->value())->toBe('First round of the shawl')
        ->and($kal->clues->all()[0]->locale->value())->toBe('ca')
        ->and($kal->clues->all()[0]->meeting->title->value())->toBe('Round 1 live session')
        ->and($kal->clues->all()[0]->meeting->timezone)->toBe('Europe/Madrid');
});

it('fails when date range is invalid', function (): void {
    $command = new CreateKalCommand(
        id: '01J5M6XQBR4GTYHN8KZXP0F1W3',
        organizerId: '01J5M6XQBR4GTYHN8KZXP0F1W2',
        name: 'Invalid KAL',
        startsOn: '2026-08-01 00:00:00',
        locales: ['ca'],
        endsOn: '2026-07-01 00:00:00',
    );

    ($this->handler)($command);
})->throws(KalException::class, 'kal_invalid_date_range');

it('fails when no locales are provided', function (): void {
    $command = new CreateKalCommand(
        id: '01J5M6XQBR4GTYHN8KZXP0F1W3',
        organizerId: '01J5M6XQBR4GTYHN8KZXP0F1W2',
        name: 'No Locale KAL',
        startsOn: '2026-08-01 00:00:00',
        locales: [],
    );

    ($this->handler)($command);
})->throws(KalException::class, 'kal_no_locales_enabled');

it('fails when organizer id is not a valid ulid', function (): void {
    $command = new CreateKalCommand(
        id: '01J5M6XQBR4GTYHN8KZXP0F1W3',
        organizerId: 'not-a-valid-ulid',
        name: 'Bad Organizer KAL',
        startsOn: '2026-08-01 00:00:00',
        locales: ['ca'],
    );

    ($this->handler)($command);
})->throws(InvalidArgumentException::class);

it('fails when a file locale is not enabled', function (): void {
    $command = new CreateKalCommand(
        id: '01J5M6XQBR4GTYHN8KZXP0F1W3',
        organizerId: '01J5M6XQBR4GTYHN8KZXP0F1W2',
        name: 'Bad File KAL',
        startsOn: '2026-08-01 00:00:00',
        locales: ['ca'],
        files: [
            [
                'fileName' => 'pattern-en.pdf',
                'filePath' => 'kals/bad/pattern-en.pdf',
                'fileSize' => 1024,
                'fileExtension' => 'pdf',
                'locale' => 'en',
                'uploadId' => '01J5M6XQBR4GTYHN8KZXP0F1W5',
                'uploadedAt' => '2026-07-25 10:00:00',
            ],
        ],
    );

    ($this->handler)($command);
})->throws(KalException::class, 'kal_file_locale_not_enabled');

it('fails when a clue payload carries no meeting', function (): void {
    $command = new CreateKalCommand(
        id: '01J5M6XQBR4GTYHN8KZXP0F1W3',
        organizerId: '01J5M6XQBR4GTYHN8KZXP0F1W2',
        name: 'Clue without meeting',
        startsOn: '2026-08-01 00:00:00',
        locales: ['ca'],
        clues: [
            [
                'name' => 'Round 1',
                'startsOn' => '2026-08-01 00:00:00',
                'endsOn' => '2026-08-08 00:00:00',
                'locale' => 'ca',
                'file' => [
                    'fileName' => 'round-1.pdf',
                    'filePath' => 'kals/no-meeting/round-1.pdf',
                    'fileSize' => 1024,
                    'fileExtension' => 'pdf',
                    'locale' => 'ca',
                    'uploadId' => '01J5M6XQBR4GTYHN8KZXP0F1W4',
                    'uploadedAt' => '2026-07-25 10:00:00',
                ],
            ],
        ],
        endsOn: '2026-09-01 00:00:00',
    );

    ($this->handler)($command);
})->throws(InvalidArgumentException::class, 'kal_invalid_payload');

it('fails when a clue payload carries no locale', function (): void {
    $command = new CreateKalCommand(
        id: '01J5M6XQBR4GTYHN8KZXP0F1W3',
        organizerId: '01J5M6XQBR4GTYHN8KZXP0F1W2',
        name: 'Clue without locale',
        startsOn: '2026-08-01 00:00:00',
        locales: ['ca'],
        clues: [
            [
                'name' => 'Round 1',
                'startsOn' => '2026-08-01 00:00:00',
                'endsOn' => '2026-08-08 00:00:00',
                'file' => [
                    'fileName' => 'round-1.pdf',
                    'filePath' => 'kals/no-locale/round-1.pdf',
                    'fileSize' => 1024,
                    'fileExtension' => 'pdf',
                    'locale' => 'ca',
                    'uploadId' => '01J5M6XQBR4GTYHN8KZXP0F1W4',
                    'uploadedAt' => '2026-07-25 10:00:00',
                ],
                'meeting' => [
                    'scheduledAt' => '2026-08-03 18:00:00',
                    'url' => 'https://zoom.us/j/round-1',
                    'title' => 'Round 1 live session',
                    'timezone' => 'Europe/Madrid',
                ],
            ],
        ],
        endsOn: '2026-09-01 00:00:00',
    );

    ($this->handler)($command);
})->throws(InvalidArgumentException::class, 'kal_invalid_payload');

it('does not persist anything when domain validation fails', function (): void {
    $command = new CreateKalCommand(
        id: '01J5M6XQBR4GTYHN8KZXP0F1W3',
        organizerId: '01J5M6XQBR4GTYHN8KZXP0F1W2',
        name: 'Invalid KAL',
        startsOn: '2026-08-01 00:00:00',
        locales: ['ca'],
        endsOn: '2026-07-01 00:00:00',
    );

    try {
        ($this->handler)($command);
    } catch (KalException) {
    }

    // Que no s'hi hagi creat cap aula de debat ho garanteix el mateix: el
    // `DbalKalRepository` les crea dins de `create()`, que aquí no s'arriba a
    // cridar.
    expect($this->repository->all())->toBeEmpty();
});
