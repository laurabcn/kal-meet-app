<?php

declare(strict_types=1);

use App\Kal\Application\Command\Clue\CreateClueCommand;
use App\Kal\Application\Command\Clue\CreateClueCommandHandler;
use App\Kal\Application\Command\Clue\DeleteClueCommand;
use App\Kal\Application\Command\Clue\DeleteClueCommandHandler;
use App\Kal\Application\Command\Clue\UpdateClueCommand;
use App\Kal\Application\Command\Clue\UpdateClueCommandHandler;
use App\Kal\Domain\Exception\ClueNotFoundException;
use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Exception\KalNotFoundException;
use App\Kal\Domain\Exception\KalStateException;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use App\Shared\Domain\ValueObject\UlidValue;
use Tests\Unit\Kal\Domain\Mother\ClueMother;
use Tests\Unit\Kal\Domain\Mother\KalMother;
use Tests\Unit\Kal\Infrastructure\Persistence\InMemoryKalRepository;

const CLUE_ORGANIZER = '01J5M6XQBR4GTYHN8KZXP0F1W2';
const CLUE_KAL = '01J5M6XQBR4GTYHN8KZXP0F1W3';
const CLUE_ID = '01J5M6XQBR4GTYHN8KZXP0F1C1';

/** @return array<string, mixed> */
function clueCommandPayload(): array
{
    return [
        'name' => 'Pista 2',
        'startsOn' => '2026-08-02 00:00:00',
        'endsOn' => '2026-08-07 00:00:00',
        'locale' => 'ca',
        'file' => [
            'fileName' => 'pista-2.pdf',
            'filePath' => 'kal/clue/pista-2.pdf',
            'fileSize' => 184320,
            'fileExtension' => 'pdf',
            'locale' => 'ca',
            'uploadId' => '01J5M6XQBR4GTYHN8KZXP0F1A1',
            'uploadedAt' => '2026-07-30 12:00:00',
        ],
        'meeting' => [
            'scheduledAt' => '2026-08-03 18:00:00',
            'url' => 'https://meet.example.com/pista-2',
            'title' => 'Trobada de la pista 2',
        ],
    ];
}

beforeEach(function (): void {
    $this->repository = new InMemoryKalRepository();
    $this->kal = KalMother::create(
        id: UlidValue::create(CLUE_KAL),
        organizerId: UlidValue::create(CLUE_ORGANIZER),
        endsOn: DateTime::create('2026-09-01 00:00:00'),
    );
    $this->repository->create($this->kal);
});

it('adds the clue to the aggregate with the id it was given', function (): void {
    (new CreateClueCommandHandler($this->repository))(
        new CreateClueCommand(CLUE_KAL, CLUE_ORGANIZER, CLUE_ID, clueCommandPayload()),
    );

    $written = $this->repository->writtenClues();
    expect($written)->toHaveCount(1)
        ->and($written[0]->id->value())->toBe(CLUE_ID)
        ->and($written[0]->name->value())->toBe('Pista 2')
        ->and($this->kal->clues->all())->toHaveCount(1);
});

it('refuses to add a clue to a kal that is not yours', function (): void {
    expect(fn () => (new CreateClueCommandHandler($this->repository))(
        new CreateClueCommand(CLUE_KAL, '01J5M6XQBR4GTYHN8KZXP0F1W9', CLUE_ID, clueCommandPayload()),
    ))->toThrow(KalNotFoundException::class, 'Kal not found.');

    expect($this->repository->writtenClues())->toBeEmpty();
});

it('refuses to add a clue outside the kal range and writes nothing', function (): void {
    $payload = clueCommandPayload();
    $payload['startsOn'] = '2026-07-01 00:00:00';
    $payload['endsOn'] = '2026-07-10 00:00:00';
    $payload['meeting']['scheduledAt'] = '2026-07-02 18:00:00';

    expect(fn () => (new CreateClueCommandHandler($this->repository))(
        new CreateClueCommand(CLUE_KAL, CLUE_ORGANIZER, CLUE_ID, $payload),
    ))->toThrow(KalException::class, 'A clue date range falls outside the kal date range.');

    expect($this->repository->writtenClues())->toBeEmpty()
        ->and($this->kal->clues->all())->toBeEmpty();
});

it('updates only the keys present in the patch', function (): void {
    $clue = ClueMother::create(name: 'Abans');
    $this->kal->addClue($clue);

    (new UpdateClueCommandHandler($this->repository))(new UpdateClueCommand(
        CLUE_KAL,
        CLUE_ORGANIZER,
        $clue->id->value(),
        ['name' => 'Després'],
    ));

    $written = $this->repository->writtenClues();
    expect($written[0]->name->value())->toBe('Després')
        ->and($written[0]->startsOn->value())->toBe($clue->startsOn->value())
        ->and($written[0]->locale->value())->toBe($clue->locale->value())
        ->and($written[0]->file->uploadId->equals($clue->file->uploadId))->toBeTrue();
});

it('clears the description when the patch sends null', function (): void {
    $clue = ClueMother::create(description: NonEmptyStringValue::create('Marxa'));
    $this->kal->addClue($clue);

    (new UpdateClueCommandHandler($this->repository))(new UpdateClueCommand(
        CLUE_KAL,
        CLUE_ORGANIZER,
        $clue->id->value(),
        ['description' => null],
    ));

    expect($this->repository->writtenClues()[0]->description)->toBeNull();
});

it('writes nothing for an empty patch but still checks the clue exists', function (): void {
    $clue = ClueMother::create();
    $this->kal->addClue($clue);

    (new UpdateClueCommandHandler($this->repository))(
        new UpdateClueCommand(CLUE_KAL, CLUE_ORGANIZER, $clue->id->value(), []),
    );
    expect($this->repository->writtenClues())->toBeEmpty();

    expect(fn () => (new UpdateClueCommandHandler($this->repository))(
        new UpdateClueCommand(CLUE_KAL, CLUE_ORGANIZER, '01J5M6XQBR4GTYHN8KZXP0F1C9', []),
    ))->toThrow(ClueNotFoundException::class, 'Clue not found.');
});

it('throws clue_not_found when patching a clue of another kal', function (): void {
    expect(fn () => (new UpdateClueCommandHandler($this->repository))(new UpdateClueCommand(
        CLUE_KAL,
        CLUE_ORGANIZER,
        '01J5M6XQBR4GTYHN8KZXP0F1C9',
        ['name' => 'Nope'],
    )))->toThrow(ClueNotFoundException::class, 'Clue not found.');
});

it('removes the clue and hands its meeting to the repository', function (): void {
    $clue = ClueMother::create();
    $this->kal->addClue($clue);

    (new DeleteClueCommandHandler($this->repository))(
        new DeleteClueCommand(CLUE_KAL, CLUE_ORGANIZER, $clue->id->value()),
    );

    expect($this->repository->deletedClueIds())->toBe([$clue->id->value()])
        ->and($this->kal->clues->all())->toBeEmpty();
});

it('throws clue_not_found when deleting a clue that is not there', function (): void {
    expect(fn () => (new DeleteClueCommandHandler($this->repository))(
        new DeleteClueCommand(CLUE_KAL, CLUE_ORGANIZER, '01J5M6XQBR4GTYHN8KZXP0F1C9'),
    ))->toThrow(ClueNotFoundException::class, 'Clue not found.');
});

it('lets a persistence failure surface instead of reporting success', function (): void {
    $this->repository->failWith(KalStateException::persistenceFailed(new RuntimeException('connection lost')));

    expect(fn () => (new CreateClueCommandHandler($this->repository))(
        new CreateClueCommand(CLUE_KAL, CLUE_ORGANIZER, CLUE_ID, clueCommandPayload()),
    ))->toThrow(KalStateException::class, 'Failed to persist the kal.');
});

it('refuses the 25th clue through the handler', function (): void {
    for ($i = 0; $i < 24; ++$i) {
        $this->kal->addClue(ClueMother::create());
    }

    expect(fn () => (new CreateClueCommandHandler($this->repository))(
        new CreateClueCommand(CLUE_KAL, CLUE_ORGANIZER, CLUE_ID, clueCommandPayload()),
    ))->toThrow(KalException::class, 'A kal cannot hold more than 24 clues.');

    expect($this->repository->writtenClues())->toBeEmpty();
});
