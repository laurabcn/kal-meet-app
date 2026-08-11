<?php

declare(strict_types=1);

use App\Kal\Application\Command\Kal\UpdateKalCommand;
use App\Kal\Application\Command\Kal\UpdateKalCommandHandler;
use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Exception\KalNotFoundException;
use App\Kal\Domain\Exception\KalStateException;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use App\Shared\Domain\ValueObject\UlidValue;
use Tests\Unit\Kal\Domain\Mother\ClueMother;
use Tests\Unit\Kal\Domain\Mother\CluesMother;
use Tests\Unit\Kal\Domain\Mother\KalMother;
use Tests\Unit\Kal\Infrastructure\Persistence\InMemoryKalRepository;

beforeEach(function (): void {
    $this->repository = new InMemoryKalRepository();
    $this->handler = new UpdateKalCommandHandler($this->repository);
});

it('updates only the fields present in the patch', function (): void {
    $kal = KalMother::create(
        id: UlidValue::create('01J5M6XQBR4GTYHN8KZXP0F1W3'),
        organizerId: UlidValue::create('01J5M6XQBR4GTYHN8KZXP0F1W2'),
        name: NonEmptyStringValue::create('Original'),
        description: NonEmptyStringValue::create('Keep me'),
        startsOn: DateTime::create('2026-08-01 00:00:00'),
        coverPath: 'keep/cover.webp',
    );
    $this->repository->create($kal);

    ($this->handler)(new UpdateKalCommand(
        id: '01J5M6XQBR4GTYHN8KZXP0F1W3',
        organizerId: '01J5M6XQBR4GTYHN8KZXP0F1W2',
        changes: ['name' => 'Patched name'],
    ));

    $updated = $this->repository->all()[0];
    expect($updated->name->value())->toBe('Patched name')
        ->and($updated->description?->value())->toBe('Keep me')
        ->and($updated->startsOn->value())->toBe('2026-08-01 00:00:00')
        ->and($updated->coverPath)->toBe('keep/cover.webp')
        ->and($updated->inviteToken->value())->toBe($kal->inviteToken->value());
});

it('clears nullable fields when the patch sends null', function (): void {
    $kal = KalMother::create(
        id: UlidValue::create('01J5M6XQBR4GTYHN8KZXP0F1W3'),
        organizerId: UlidValue::create('01J5M6XQBR4GTYHN8KZXP0F1W2'),
        description: NonEmptyStringValue::create('Gone'),
        endsOn: DateTime::create('2026-09-01 00:00:00'),
        coverPath: 'gone.webp',
    );
    $this->repository->create($kal);

    ($this->handler)(new UpdateKalCommand(
        id: '01J5M6XQBR4GTYHN8KZXP0F1W3',
        organizerId: '01J5M6XQBR4GTYHN8KZXP0F1W2',
        changes: [
            'description' => null,
            'endsOn' => null,
            'coverPath' => null,
        ],
    ));

    $updated = $this->repository->all()[0];
    expect($updated->description)->toBeNull()
        ->and($updated->endsOn)->toBeNull()
        ->and($updated->coverPath)->toBeNull();
});

it('does not persist when the patch has no changes', function (): void {
    $kal = KalMother::create(
        id: UlidValue::create('01J5M6XQBR4GTYHN8KZXP0F1W3'),
        organizerId: UlidValue::create('01J5M6XQBR4GTYHN8KZXP0F1W2'),
        name: NonEmptyStringValue::create('Unchanged'),
    );
    $this->repository->create($kal);

    ($this->handler)(new UpdateKalCommand(
        id: '01J5M6XQBR4GTYHN8KZXP0F1W3',
        organizerId: '01J5M6XQBR4GTYHN8KZXP0F1W2',
        changes: [],
    ));

    // Un patch buit no toca res: el KAL segueix sense haver-se actualitzat mai.
    expect($this->repository->all()[0]->name->value())->toBe('Unchanged')
        ->and($this->repository->all()[0]->updatedAt)->toBeNull();
});

it('throws kal_not_found when the kal does not exist', function (): void {
    expect(fn () => ($this->handler)(new UpdateKalCommand(
        id: '01J5M6XQBR4GTYHN8KZXP0F1W3',
        organizerId: '01J5M6XQBR4GTYHN8KZXP0F1W2',
        changes: ['name' => 'Nope'],
    )))->toThrow(KalNotFoundException::class, 'Kal not found.');
});

it('throws kal_not_found when the caller is not the organizer', function (): void {
    $kal = KalMother::create(
        id: UlidValue::create('01J5M6XQBR4GTYHN8KZXP0F1W3'),
        organizerId: UlidValue::create('01J5M6XQBR4GTYHN8KZXP0F1W2'),
    );
    $this->repository->create($kal);

    expect(fn () => ($this->handler)(new UpdateKalCommand(
        id: '01J5M6XQBR4GTYHN8KZXP0F1W3',
        organizerId: '01J5M6XQBR4GTYHN8KZXP0F1W9',
        changes: ['name' => 'Nope'],
    )))->toThrow(KalNotFoundException::class, 'Kal not found.');
});

it('rejects shrinking the range below an existing clue', function (): void {
    $clue = ClueMother::create(
        startsOn: DateTime::create('2026-08-01 00:00:00'),
        endsOn: DateTime::create('2026-08-15 00:00:00'),
    );
    $kal = KalMother::create(
        id: UlidValue::create('01J5M6XQBR4GTYHN8KZXP0F1W3'),
        organizerId: UlidValue::create('01J5M6XQBR4GTYHN8KZXP0F1W2'),
        clues: CluesMother::of($clue),
        endsOn: DateTime::create('2026-09-01 00:00:00'),
    );
    $this->repository->create($kal);

    expect(fn () => ($this->handler)(new UpdateKalCommand(
        id: '01J5M6XQBR4GTYHN8KZXP0F1W3',
        organizerId: '01J5M6XQBR4GTYHN8KZXP0F1W2',
        changes: ['endsOn' => '2026-08-10 00:00:00'],
    )))->toThrow(KalException::class, 'A clue date range falls outside the kal date range.');
});

it('lets a persistence failure surface instead of reporting success', function (): void {
    $kal = KalMother::create(
        id: UlidValue::create('01J5M6XQBR4GTYHN8KZXP0F1W3'),
        organizerId: UlidValue::create('01J5M6XQBR4GTYHN8KZXP0F1W2'),
    );
    $this->repository->create($kal);
    $this->repository->failWith(KalStateException::persistenceFailed(new RuntimeException('connection lost')));

    expect(fn () => ($this->handler)(new UpdateKalCommand(
        id: '01J5M6XQBR4GTYHN8KZXP0F1W3',
        organizerId: '01J5M6XQBR4GTYHN8KZXP0F1W2',
        changes: ['name' => 'Doomed'],
    )))->toThrow(KalStateException::class, 'Failed to persist the kal.');
});
