<?php

declare(strict_types=1);

use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Exception\KalStateException;
use App\Kal\Infrastructure\Repository\MySQL\Hydrator\KalHydrator;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use Tests\Unit\Kal\Domain\Mother\ClueMother;
use Tests\Unit\Kal\Domain\Mother\CluesMother;
use Tests\Unit\Kal\Domain\Mother\FileMother;
use Tests\Unit\Kal\Domain\Mother\FilesMother;
use Tests\Unit\Kal\Domain\Mother\KalMother;
use Tests\Unit\Kal\Domain\Mother\MeetingMother;
use Tests\Unit\Kal\Domain\Mother\MeetingsMother;
use Tests\Unit\Shared\Domain\ValueObject\Mother\LocaleMother;

beforeEach(function (): void {
    $this->hydrator = new KalHydrator();
});

/**
 * @param array{
 *     kal: array<string, mixed>,
 *     locales: list<array{kal_id: string, locale: string}>,
 *     files: list<array<string, mixed>>,
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
        'locales' => array_column($extracted['locales'], 'locale'),
        'files' => $extracted['files'],
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
        files: FilesMother::of(FileMother::create()),
        clues: CluesMother::of(ClueMother::create()),
        meetings: MeetingsMother::of(MeetingMother::create()),
    );

    $extracted = $this->hydrator->extract($kal);
    $kalId = $kal->id->value();
    $clue = $kal->clues->all()[0];
    $file = $kal->files->all()[0];
    $meeting = $kal->meetings->all()[0];

    expect($extracted['kal'])->toBe([
        'id' => $kalId,
        'organizer_id' => $kal->organizerId->value(),
        'name' => $kal->name->value(),
        'description' => 'Smoke',
        'starts_on' => $kal->startsOn->value(),
        'ends_on' => $kal->endsOn?->value(),
        'cover_path' => 'kals/id/portada.webp',
        'invite_token' => $kal->inviteToken->value(),
        'created_at' => $kal->createdAt->value(),
        'updated_at' => $kal->updatedAt->value(),
    ])
        ->and($extracted['locales'])->toHaveCount(2)
        ->and($extracted['locales'][0])->toHaveKeys(['kal_id', 'locale'])
        ->and($extracted['locales'][0]['kal_id'])->toBe($kalId)
        ->and($extracted['files'])->toHaveCount(1)
        ->and($extracted['files'][0])->toMatchArray([
            'upload_id' => $file->uploadId->value(),
            'kal_id' => $kalId,
            'file_name' => $file->fileName->value(),
            'file_path' => $file->filePath->value(),
            'file_size' => $file->fileSize->value(),
            'file_extension' => $file->fileExtension->value(),
            'locale' => $file->locale->value(),
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
        files: FilesMother::of(FileMother::create(LocaleMother::catalan())),
        clues: CluesMother::of(ClueMother::create(locale: LocaleMother::catalan())),
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
        ->and($hydrated->updatedAt->value())->toBe($kal->updatedAt->value())
        ->and(array_map(static fn ($locale) => $locale->value(), $hydrated->locales->all()))
            ->toEqualCanonicalizing(array_map(static fn ($locale) => $locale->value(), $kal->locales->all()))
        ->and($hydrated->files->all())->toHaveCount(1)
        ->and($hydrated->files->all()[0]->uploadId->equals($kal->files->all()[0]->uploadId))->toBeTrue()
        ->and($hydrated->files->all()[0]->fileName->value())->toBe($kal->files->all()[0]->fileName->value())
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
        ->and($hydrated->files->all())->toBeEmpty()
        ->and($hydrated->clues->all())->toBeEmpty()
        ->and($hydrated->meetings->all())->toBeEmpty();
});

it('hydrates dates from DateTimeInterface values', function (): void {
    $kal = KalMother::create();
    $payload = hydratePayloadFromExtract($this->hydrator->extract($kal));
    $payload['starts_on'] = new DateTimeImmutable($kal->startsOn->value(), new DateTimeZone('UTC'));
    $payload['created_at'] = new DateTimeImmutable($kal->createdAt->value(), new DateTimeZone('UTC'));
    $payload['updated_at'] = new DateTimeImmutable($kal->updatedAt->value(), new DateTimeZone('UTC'));

    $hydrated = $this->hydrator->hydrate($payload);

    expect($hydrated->startsOn->value())->toBe($kal->startsOn->value())
        ->and($hydrated->createdAt->value())->toBe($kal->createdAt->value())
        ->and($hydrated->updatedAt->value())->toBe($kal->updatedAt->value());
});

it('hydrates file_size from a numeric string', function (): void {
    $kal = KalMother::create(files: FilesMother::of(FileMother::create()));
    $payload = hydratePayloadFromExtract($this->hydrator->extract($kal));
    $payload['files'][0]['file_size'] = (string) $kal->files->all()[0]->fileSize->value();

    $hydrated = $this->hydrator->hydrate($payload);

    expect($hydrated->files->all()[0]->fileSize->value())->toBe($kal->files->all()[0]->fileSize->value());
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

it('throws when locales are empty', function (): void {
    $kal = KalMother::create();
    $payload = hydratePayloadFromExtract($this->hydrator->extract($kal));
    $payload['locales'] = [];

    expect(fn () => $this->hydrator->hydrate($payload))
        ->toThrow(KalException::class, 'At least one locale must be enabled for the kal.');
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
        ->toThrow(KalStateException::class, 'The kal is missing its debate room.');
});
