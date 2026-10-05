<?php

declare(strict_types=1);

use App\Kal\Application\Command\Kal\ReplaceKalFileCommand;
use App\Kal\Application\Command\Kal\ReplaceKalFileCommandHandler;
use App\Kal\Domain\Exception\KalNotFoundException;
use App\Kal\Domain\Exception\KalStateException;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\UlidValue;
use Tests\Unit\Kal\Domain\Mother\FileMother;
use Tests\Unit\Kal\Domain\Mother\KalMother;
use Tests\Unit\Kal\Infrastructure\Persistence\InMemoryKalRepository;

/** @return array<string, mixed> */
function replaceFilePayload(): array
{
    return [
        'fileName' => 'patro.pdf',
        'filePath' => '01J5M6XQBR4GTYHN8KZXP0F1W3/ca.pdf',
        'fileSize' => 184320,
        'fileExtension' => 'pdf',
        'uploadId' => '01J5M6XQBR4GTYHN8KZXP0F1B1',
        'uploadedAt' => '2026-10-03 12:00:00',
    ];
}

beforeEach(function (): void {
    $this->kalId = '01J5M6XQBR4GTYHN8KZXP0F1W3';
    $this->organizerId = '01J5M6XQBR4GTYHN8KZXP0F1W2';
    $this->repository = new InMemoryKalRepository();
    $this->handler = new ReplaceKalFileCommandHandler($this->repository);
});

it('sets the pattern file of a kal that had none', function (): void {
    $kal = KalMother::create(id: UlidValue::create($this->kalId), organizerId: UlidValue::create($this->organizerId));
    $this->repository->create($kal);

    ($this->handler)(new ReplaceKalFileCommand($this->kalId, $this->organizerId, replaceFilePayload()));

    expect($this->repository->fileReplacements())->toBe(1)
        ->and($kal->file?->uploadId->value())->toBe('01J5M6XQBR4GTYHN8KZXP0F1B1')
        ->and($kal->file?->filePath->value())->toBe('01J5M6XQBR4GTYHN8KZXP0F1W3/ca.pdf');
});

it('replaces the pattern file a kal already had', function (): void {
    $kal = KalMother::create(
        id: UlidValue::create($this->kalId),
        organizerId: UlidValue::create($this->organizerId),
        file: FileMother::create('old.pdf'),
    );
    $this->repository->create($kal);

    ($this->handler)(new ReplaceKalFileCommand($this->kalId, $this->organizerId, replaceFilePayload()));

    expect($this->repository->fileReplacements())->toBe(1)
        ->and($kal->file?->fileName->value())->toBe('patro.pdf');
});

it('writes nothing when the file sent is the live one', function (): void {
    $kal = KalMother::create(id: UlidValue::create($this->kalId), organizerId: UlidValue::create($this->organizerId));
    $this->repository->create($kal);
    $command = new ReplaceKalFileCommand($this->kalId, $this->organizerId, replaceFilePayload());
    ($this->handler)($command);
    $updatedAt = $kal->updatedAt?->value();

    ($this->handler)($command);

    expect($this->repository->fileReplacements())->toBe(1)
        ->and($kal->updatedAt?->value())->toBe($updatedAt);
});

it('answers kal_not_found to someone who is not the organizer, before looking at the file', function (): void {
    $kal = KalMother::create(id: UlidValue::create($this->kalId), organizerId: UlidValue::create($this->organizerId));
    $this->repository->create($kal);
    $invalid = replaceFilePayload();
    $invalid['locale'] = 'ca';

    // Amb un payload invàlid a propòsit: si el 400 sortís abans que el 404,
    // qualsevol podria saber quins KALs existeixen.
    expect(fn () => ($this->handler)(
        new ReplaceKalFileCommand($this->kalId, '01J5M6XQBR4GTYHN8KZXP0F1W9', $invalid),
    ))->toThrow(KalNotFoundException::class, 'Kal not found.');

    expect($this->repository->fileReplacements())->toBe(0)
        ->and($kal->file)->toBeNull();
});

it('answers kal_not_found for a deleted kal', function (): void {
    $kal = KalMother::create(id: UlidValue::create($this->kalId), organizerId: UlidValue::create($this->organizerId));
    $this->repository->create($kal);
    $this->repository->softDelete($this->kalId);

    expect(fn () => ($this->handler)(
        new ReplaceKalFileCommand($this->kalId, $this->organizerId, replaceFilePayload()),
    ))->toThrow(KalNotFoundException::class, 'Kal not found.');

    expect($this->repository->fileReplacements())->toBe(0);
});

it('rejects a file that carries its own locale and keeps the live one', function (): void {
    $live = FileMother::create('live.pdf');
    $kal = KalMother::create(
        id: UlidValue::create($this->kalId),
        organizerId: UlidValue::create($this->organizerId),
        file: $live,
    );
    $this->repository->create($kal);
    $payload = replaceFilePayload();
    $payload['locale'] = 'ca';

    expect(fn () => ($this->handler)(
        new ReplaceKalFileCommand($this->kalId, $this->organizerId, $payload),
    ))->toThrow(InvalidArgumentException::class, 'The request payload is invalid.');

    expect($this->repository->fileReplacements())->toBe(0)
        ->and($kal->file?->uploadId->equals($live->uploadId))->toBeTrue();
});

it('lets a persistence failure surface instead of reporting success', function (): void {
    $kal = KalMother::create(id: UlidValue::create($this->kalId), organizerId: UlidValue::create($this->organizerId));
    $this->repository->create($kal);
    $this->repository->failWith(KalStateException::persistenceFailed(new RuntimeException('connection lost')));

    expect(fn () => ($this->handler)(
        new ReplaceKalFileCommand($this->kalId, $this->organizerId, replaceFilePayload()),
    ))->toThrow(KalStateException::class, 'Failed to persist the kal.');
});
