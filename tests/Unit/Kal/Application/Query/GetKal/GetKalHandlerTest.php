<?php

declare(strict_types=1);

use App\Kal\Application\Query\GetKal\GetKalQuery;
use App\Kal\Application\Query\GetKal\GetKalQueryHandler;
use App\Kal\Domain\Exception\KalNotFoundException;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\UlidValue;
use Tests\Unit\Kal\Domain\Mother\ClueMother;
use Tests\Unit\Kal\Domain\Mother\CluesMother;
use Tests\Unit\Kal\Domain\Mother\FileMother;
use Tests\Unit\Kal\Domain\Mother\KalMother;
use Tests\Unit\Kal\Domain\Mother\MeetingMother;
use Tests\Unit\Kal\Domain\Mother\MeetingsMother;
use Tests\Unit\Kal\Infrastructure\Persistence\InMemoryKalRepository;

beforeEach(function (): void {
    $this->repository = new InMemoryKalRepository();
    $this->handler = new GetKalQueryHandler($this->repository);
});

it('returns the kal snapshot including the invite token when the caller organizes it', function (): void {
    $organizerId = UlidValue::generate();
    $kal = KalMother::create(organizerId: $organizerId);
    $this->repository->create($kal);

    $response = ($this->handler)(new GetKalQuery($kal->id->value(), $organizerId->value()));

    $result = $response->result();
    expect($result['id'])->toBe($kal->id->value())
        ->and($result['name'])->toBe($kal->name->value())
        ->and($result['inviteToken'])->toBe($kal->inviteToken->value())
        ->and($result['inviteToken'])->not->toBeEmpty()
        ->and($result)->not->toHaveKey('organizerId')
        ->and($result['meetings'])->toBeEmpty()
        ->and($result['clues'])->toBeEmpty()
        ->and($result['locale'])->toBe($kal->locale->value())
        ->and($result['file'])->toBeNull();
});

it('serializes nested meetings, clues and the file mirroring the create graph', function (): void {
    $organizerId = UlidValue::generate();
    $kal = KalMother::create(
        file: FileMother::create(),
        clues: CluesMother::of(ClueMother::create()),
        organizerId: $organizerId,
        meetings: MeetingsMother::of(MeetingMother::create()),
    );
    $this->repository->create($kal);

    $result = ($this->handler)(new GetKalQuery($kal->id->value(), $organizerId->value()))->result();

    $meeting = $kal->meetings->all()[0];
    $clue = $kal->clues->all()[0];
    $file = $kal->file;
    assert(null !== $file);

    expect($result['meetings'])->toHaveCount(1)
        ->and($result['meetings'][0])->toBe([
            'id' => $meeting->id->value(),
            'scheduledAt' => $meeting->scheduledAt->value(),
            'url' => $meeting->url->value(),
            'title' => $meeting->title->value(),
            'timezone' => $meeting->timezone,
        ])
        ->and($result['clues'])->toHaveCount(1)
        ->and($result['clues'][0]['id'])->toBe($clue->id->value())
        ->and($result['clues'][0]['name'])->toBe($clue->name->value())
        ->and($result['clues'][0]['file']['fileName'])->toBe($clue->file->fileName->value())
        ->and($result['clues'][0]['meeting']['id'])->toBe($clue->meeting->id->value())
        ->and($result['file'])->toBe([
            'fileName' => $file->fileName->value(),
            'filePath' => $file->filePath->value(),
            'fileSize' => $file->fileSize->value(),
            'fileExtension' => $file->fileExtension->value(),
            'uploadId' => $file->uploadId->value(),
            'uploadedAt' => $file->uploadedAt->value(),
        ]);
});

it('throws kal_not_found when no kal exists for the given id', function (): void {
    ($this->handler)(new GetKalQuery(UlidValue::generate()->value(), UlidValue::generate()->value()));
})->throws(KalNotFoundException::class, 'Kal not found.');

it('throws kal_not_found when the caller is not the organizer, without revealing existence', function (): void {
    $kal = KalMother::create();
    $this->repository->create($kal);

    ($this->handler)(new GetKalQuery($kal->id->value(), UlidValue::generate()->value()));
})->throws(KalNotFoundException::class, 'Kal not found.');

it('throws kal_not_found for a soft-deleted kal', function (): void {
    $organizerId = UlidValue::generate();
    $kal = KalMother::create(organizerId: $organizerId);
    $this->repository->create($kal);
    $this->repository->softDelete($kal->id->value());

    ($this->handler)(new GetKalQuery($kal->id->value(), $organizerId->value()));
})->throws(KalNotFoundException::class, 'Kal not found.');

it('fails when the id is not a valid ulid', function (): void {
    ($this->handler)(new GetKalQuery('not-a-valid-ulid', UlidValue::generate()->value()));
})->throws(InvalidArgumentException::class);
