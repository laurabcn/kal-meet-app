<?php

declare(strict_types=1);

use App\Kal\Domain\Exception\KalStateException;
use App\Kal\Infrastructure\Repository\MySQL\Hydrator\KalHydrator;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use Tests\Unit\Kal\Domain\Mother\ClueMother;
use Tests\Unit\Kal\Domain\Mother\CluesMother;
use Tests\Unit\Kal\Domain\Mother\FileMother;
use Tests\Unit\Kal\Domain\Mother\KalMother;
use Tests\Unit\Kal\Domain\Mother\MeetingMother;
use Tests\Unit\Kal\Domain\Mother\MeetingsMother;

beforeEach(function (): void {
    $this->hydrator = new KalHydrator();
});

/**
 * @param array{
 *     kal: array<string, mixed>,
 *     file: ?array<string, mixed>,
 *     clues: list<array<string, mixed>>,
 *     meetings: list<array<string, mixed>>,
 *     debate_room: array<string, mixed>
 * } $extracted
 *
 * @return array<string, mixed>
 */
function hydratePayloadFromExtract(array $extracted): array
{
    return [
        ...$extracted['kal'],
        // El repositori llegeix `kal_files` com a llista (un SELECT per
        // kal_id), encara que en pugui haver com a molt una de viva.
        'files' => null !== $extracted['file'] ? [$extracted['file']] : [],
        'clues' => $extracted['clues'],
        'meetings' => $extracted['meetings'],
        // El repositori la llegeix com a llista (un SELECT per kal_id) encara
        // que a l'MVP només n'hi hagi una.
        'debate_rooms' => [$extracted['debate_room']],
    ];
}

it('extracts the kal row and child rows ready for insert', function (): void {
    $kal = KalMother::create(
        description: NonEmptyStringValue::create('Smoke'),
        endsOn: DateTime::create('2026-09-01 00:00:00'),
        coverPath: 'kals/id/portada.webp',
        file: FileMother::create(),
        clues: CluesMother::of(ClueMother::create()),
        meetings: MeetingsMother::of(MeetingMother::create()),
    );

    $extracted = $this->hydrator->extract($kal);
    $kalId = $kal->id->value();
    $clue = $kal->clues->all()[0];
    $file = $kal->file;
    assert(null !== $file);
    $meeting = $kal->meetings->all()[0];

    expect($extracted['kal'])->toBe([
        'id' => $kalId,
        'organizer_id' => $kal->organizerId->value(),
        'name' => $kal->name->value(),
        'description' => 'Smoke',
        'starts_on' => $kal->startsOn->value(),
        'ends_on' => $kal->endsOn?->value(),
        'cover_path' => 'kals/id/portada.webp',
        'locale' => $kal->locale->value(),
        'invite_token' => $kal->inviteToken->value(),
        'created_at' => $kal->createdAt->value(),
        // `kals.updated_at` és NOT NULL: sense haver-se actualitzat mai,
        // l'extract hi escriu la data de creació.
        'updated_at' => $kal->createdAt->value(),
    ])
        ->and($extracted['file'])->toMatchArray([
            'upload_id' => $file->uploadId->value(),
            'kal_id' => $kalId,
            'file_name' => $file->fileName->value(),
            'file_path' => $file->filePath->value(),
            'file_size' => $file->fileSize->value(),
            'file_extension' => $file->fileExtension->value(),
        ])
        ->and($extracted['clues'])->toHaveCount(1)
        ->and($extracted['clues'][0]['id'])->toBe($clue->id->value())
        ->and($extracted['clues'][0]['kal_id'])->toBe($kalId)
        ->and($extracted['clues'][0]['file_upload_id'])->toBe($clue->file->uploadId->value())
        ->and($extracted['meetings'])->toHaveCount(2)
        ->and($extracted['meetings'][0])->toMatchArray([
            'id' => $meeting->id->value(),
            'kal_id' => $kalId,
            'clue_id' => null,
            'title' => $meeting->title->value(),
            'url' => $meeting->url->value(),
            'timezone' => $meeting->timezone,
        ])
        ->and($extracted['meetings'][1]['clue_id'])->toBe($clue->id->value())
        ->and($extracted['meetings'][1]['id'])->toBe($clue->meeting->id->value());
});

it('rejects extract of a non-kal object', function (): void {
    expect(fn () => $this->hydrator->extract(new stdClass()))
        ->toThrow(KalStateException::class, 'The value is not a valid kal.');
});

it('round-trips a full aggregate through extract and hydrate', function (): void {
    $kal = KalMother::create(
        description: NonEmptyStringValue::create('Round trip'),
        endsOn: DateTime::create('2026-09-01 00:00:00'),
        coverPath: 'kals/id/portada.webp',
        file: FileMother::create(),
        clues: CluesMother::of(ClueMother::create()),
        meetings: MeetingsMother::of(MeetingMother::create()),
    );

    $hydrated = $this->hydrator->hydrate(hydratePayloadFromExtract($this->hydrator->extract($kal)));

    expect($hydrated->id->equals($kal->id))->toBeTrue()
        ->and($hydrated->organizerId->equals($kal->organizerId))->toBeTrue()
        ->and($hydrated->name->value())->toBe($kal->name->value())
        ->and($hydrated->description?->value())->toBe('Round trip')
        ->and($hydrated->coverPath)->toBe('kals/id/portada.webp')
        ->and($hydrated->inviteToken->equals($kal->inviteToken))->toBeTrue()
        ->and($hydrated->startsOn->value())->toBe($kal->startsOn->value())
        ->and($hydrated->endsOn?->value())->toBe($kal->endsOn?->value())
        ->and($hydrated->createdAt->value())->toBe($kal->createdAt->value())
        // L'anada i tornada passa per una columna NOT NULL, o sigui que un
        // `updatedAt` null torna com la data de creació: és el que hi ha desat.
        ->and($hydrated->updatedAt?->value())->toBe($kal->createdAt->value())
        ->and($hydrated->locale->equals($kal->locale))->toBeTrue()
        ->and($hydrated->file?->uploadId->value())->toBe($kal->file?->uploadId->value())
        ->and($hydrated->file?->fileName->value())->toBe($kal->file?->fileName->value())
        ->and($hydrated->clues->all())->toHaveCount(1)
        ->and($hydrated->clues->all()[0]->id->equals($kal->clues->all()[0]->id))->toBeTrue()
        ->and($hydrated->clues->all()[0]->meeting->id->equals($kal->clues->all()[0]->meeting->id))->toBeTrue()
        ->and($hydrated->clues->all()[0]->file->fileName->value())->toBe($kal->clues->all()[0]->file->fileName->value())
        ->and($hydrated->meetings->all())->toHaveCount(1)
        ->and($hydrated->meetings->all()[0]->id->equals($kal->meetings->all()[0]->id))->toBeTrue();
});

it('hydrates nullable kal fields as null when absent', function (): void {
    $kal = KalMother::create();
    $payload = hydratePayloadFromExtract($this->hydrator->extract($kal));

    $hydrated = $this->hydrator->hydrate($payload);

    expect($hydrated->description)->toBeNull()
        ->and($hydrated->endsOn)->toBeNull()
        ->and($hydrated->coverPath)->toBeNull()
        ->and($hydrated->file)->toBeNull()
        ->and($hydrated->clues->all())->toBeEmpty()
        ->and($hydrated->meetings->all())->toBeEmpty();
});

it('hydrates dates from DateTimeInterface values', function (): void {
    $kal = KalMother::create();
    $payload = hydratePayloadFromExtract($this->hydrator->extract($kal));
    $payload['starts_on'] = new DateTimeImmutable($kal->startsOn->value(), new DateTimeZone('UTC'));
    $payload['created_at'] = new DateTimeImmutable($kal->createdAt->value(), new DateTimeZone('UTC'));
    $payload['updated_at'] = new DateTimeImmutable($kal->createdAt->value(), new DateTimeZone('UTC'));

    $hydrated = $this->hydrator->hydrate($payload);

    expect($hydrated->startsOn->value())->toBe($kal->startsOn->value())
        ->and($hydrated->createdAt->value())->toBe($kal->createdAt->value())
        ->and($hydrated->updatedAt?->value())->toBe($kal->createdAt->value());
});

it('hydrates file_size from a numeric string', function (): void {
    $file = FileMother::create();
    $kal = KalMother::create(file: $file);
    $payload = hydratePayloadFromExtract($this->hydrator->extract($kal));
    $payload['files'][0]['file_size'] = (string) $file->fileSize->value();

    $hydrated = $this->hydrator->hydrate($payload);

    expect($hydrated->file?->fileSize->value())->toBe($file->fileSize->value());
});

it('throws when a clue row has no matching meeting', function (): void {
    $kal = KalMother::create(clues: CluesMother::of(ClueMother::create()));
    $payload = hydratePayloadFromExtract($this->hydrator->extract($kal));
    $payload['meetings'] = array_values(array_filter(
        $payload['meetings'],
        static fn (array $meeting): bool => null === ($meeting['clue_id'] ?? null),
    ));

    expect(fn () => $this->hydrator->hydrate($payload))
        ->toThrow(KalStateException::class, 'A clue is missing its required meeting.');
});

it('throws when the kal row has no locale', function (): void {
    $kal = KalMother::create();
    $payload = hydratePayloadFromExtract($this->hydrator->extract($kal));
    $payload['locale'] = null;

    expect(fn () => $this->hydrator->hydrate($payload))
        ->toThrow(InvalidArgumentException::class);
});

it('throws when the kal has more than one active file', function (): void {
    $kal = KalMother::create(file: FileMother::create());
    $payload = hydratePayloadFromExtract($this->hydrator->extract($kal));
    $payload['files'][] = $payload['files'][0];

    expect(fn () => $this->hydrator->hydrate($payload))
        ->toThrow(KalStateException::class, 'The kal has more than one active file.');
});

it('throws when a required string field is not a string', function (): void {
    $kal = KalMother::create();
    $payload = hydratePayloadFromExtract($this->hydrator->extract($kal));
    $payload['name'] = 123;

    expect(fn () => $this->hydrator->hydrate($payload))
        ->toThrow(InvalidArgumentException::class);
});

it('throws when id is not a valid ulid', function (): void {
    $kal = KalMother::create();
    $payload = hydratePayloadFromExtract($this->hydrator->extract($kal));
    $payload['id'] = 'not-a-ulid';

    expect(fn () => $this->hydrator->hydrate($payload))
        ->toThrow(InvalidArgumentException::class);
});

it('extracts the debate room row ready for insert', function (): void {
    $kal = KalMother::create();

    $extracted = (new KalHydrator())->extract($kal);

    expect($extracted['debate_room'])->toMatchArray([
        'id' => $kal->debateRoom->id->value(),
        'kal_id' => $kal->id->value(),
    ])->and($extracted['debate_room']['created_at'])->not->toBeEmpty();
});

// Un KAL persistit sense aula és il·legible a propòsit: val més fallar fort que
// servir a la participant un KAL sense la pantalla on ha d\'aterrar.
it('refuses to hydrate a kal with no debate room', function (): void {
    $payload = hydratePayloadFromExtract((new KalHydrator())->extract(KalMother::create()));
    $payload['debate_rooms'] = [];

    expect(fn () => (new KalHydrator())->hydrate($payload))
        ->toThrow(KalStateException::class, 'The kal does not have exactly one debate room.');
});

it('refuses to hydrate a kal with more than one debate room', function (): void {
    $payload = hydratePayloadFromExtract((new KalHydrator())->extract(KalMother::create()));
    $payload['debate_rooms'][] = $payload['debate_rooms'][0];

    expect(fn () => (new KalHydrator())->hydrate($payload))
        ->toThrow(KalStateException::class, 'The kal does not have exactly one debate room.');
});
