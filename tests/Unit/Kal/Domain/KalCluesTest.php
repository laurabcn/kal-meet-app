<?php

declare(strict_types=1);

use App\Kal\Domain\Exception\ClueNotFoundException;
use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Kal;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use App\Shared\Domain\ValueObject\UlidValue;
use Tests\Unit\Kal\Domain\Mother\ClueMother;
use Tests\Unit\Kal\Domain\Mother\CluesMother;
use Tests\Unit\Kal\Domain\Mother\KalMother;
use Tests\Unit\Kal\Domain\Mother\LocalesMother;
use Tests\Unit\Shared\Domain\ValueObject\Mother\LocaleMother;

it('updates the scalar fields of a clue and keeps its identity, file and meeting', function (): void {
    $clue = ClueMother::create(name: 'Abans');
    $kal = KalMother::create(clues: CluesMother::of($clue), endsOn: DateTime::create('2026-09-01 00:00:00'));

    $updated = $kal->updateClue(
        $clue->id,
        NonEmptyStringValue::create('Després'),
        NonEmptyStringValue::create('Nova descripció'),
        $clue->startsOn,
        $clue->endsOn,
        $clue->locale,
    );

    expect($updated->id->equals($clue->id))->toBeTrue()
        ->and($updated->name->value())->toBe('Després')
        ->and($updated->description?->value())->toBe('Nova descripció')
        ->and($updated->file->uploadId->equals($clue->file->uploadId))->toBeTrue()
        ->and($updated->meeting->id->equals($clue->meeting->id))->toBeTrue()
        ->and($kal->clues->all()[0]->name->value())->toBe('Després');
});

it('bumps updatedAt when a clue changes', function (): void {
    $clue = ClueMother::create();
    $kal = KalMother::create(clues: CluesMother::of($clue));

    $updated = $kal->updateClue(
        $clue->id,
        NonEmptyStringValue::create('Nou nom'),
        null,
        $clue->startsOn,
        $clue->endsOn,
        $clue->locale,
    );

    expect($updated->updatedAt->value())->toBeGreaterThanOrEqual($clue->updatedAt->value());
});

it('refuses to update a clue that is not in the kal', function (): void {
    $kal = KalMother::create(clues: CluesMother::of(ClueMother::create()));

    expect(fn () => $kal->updateClue(
        UlidValue::generate(),
        NonEmptyStringValue::create('Nope'),
        null,
        DateTime::create('2026-08-01 00:00:00'),
        DateTime::create('2026-08-08 00:00:00'),
        LocaleMother::catalan(),
    ))->toThrow(ClueNotFoundException::class, 'Clue not found.');
});

it('refuses an update that pushes the clue outside the kal range', function (): void {
    $clue = ClueMother::create();
    $kal = KalMother::create(
        clues: CluesMother::of($clue),
        endsOn: DateTime::create('2026-08-31 00:00:00'),
    );

    expect(fn () => $kal->updateClue(
        $clue->id,
        $clue->name,
        null,
        $clue->startsOn,
        DateTime::create('2026-09-30 00:00:00'),
        $clue->locale,
    ))->toThrow(KalException::class, 'A clue date range falls outside the kal date range.');
});

it('refuses an update that leaves the meeting outside the clue range', function (): void {
    // La reunió cau al principi de la pista; escurçar-la per l'esquerra la deixa fora.
    $clue = ClueMother::create(
        startsOn: DateTime::create('2026-08-01 00:00:00'),
        endsOn: DateTime::create('2026-08-20 00:00:00'),
    );
    $kal = KalMother::create(clues: CluesMother::of($clue), endsOn: DateTime::create('2026-09-01 00:00:00'));

    expect(fn () => $kal->updateClue(
        $clue->id,
        $clue->name,
        null,
        DateTime::create('2026-08-10 00:00:00'),
        $clue->endsOn,
        $clue->locale,
    ))->toThrow(KalException::class, 'A meeting is scheduled outside its clue date range.');
});

it('refuses an update to a locale the kal has not enabled', function (): void {
    $clue = ClueMother::create();
    $kal = KalMother::create(clues: CluesMother::of($clue), locales: LocalesMother::catalanAndSpanish());

    expect(fn () => $kal->updateClue(
        $clue->id,
        $clue->name,
        null,
        $clue->startsOn,
        $clue->endsOn,
        LocaleMother::english(),
    ))->toThrow(KalException::class, 'A clue locale is not enabled for this kal.');
});

it('removes a clue and hands it back so its meeting can fall with it', function (): void {
    $clue = ClueMother::create();
    $kal = KalMother::create(clues: CluesMother::of($clue));

    $removed = $kal->removeClue($clue->id);

    expect($removed->id->equals($clue->id))->toBeTrue()
        ->and($removed->meeting->id->equals($clue->meeting->id))->toBeTrue()
        ->and($kal->clues->all())->toBeEmpty();
});

it('refuses to remove a clue that is not in the kal', function (): void {
    $kal = KalMother::create(clues: CluesMother::of(ClueMother::create()));

    expect(fn () => $kal->removeClue(UlidValue::generate()))
        ->toThrow(ClueNotFoundException::class, 'Clue not found.');
});

it('keeps the remaining clues as a list after a removal', function (): void {
    $first = ClueMother::create(name: 'Una');
    $second = ClueMother::create(name: 'Dues');
    $kal = KalMother::create(clues: CluesMother::of($first, $second));

    $kal->removeClue($first->id);

    // Sense reindexar, la clau 1 sobreviuria i el JSON sortiria com a objecte.
    expect(array_keys($kal->clues->all()))->toBe([0])
        ->and($kal->clues->all()[0]->name->value())->toBe('Dues');
});

it('accepts the 24th clue and refuses the 25th', function (): void {
    $kal = KalMother::create(endsOn: DateTime::create('2026-12-01 00:00:00'));

    for ($i = 0; $i < Kal::MAX_CLUES; ++$i) {
        $kal->addClue(ClueMother::create());
    }

    expect($kal->clues->count())->toBe(24)
        ->and(fn () => $kal->addClue(ClueMother::create()))
        ->toThrow(KalException::class, 'A kal cannot hold more than 24 clues.');
});

it('refuses to create a kal already over the clue limit', function (): void {
    $clues = [];
    for ($i = 0; $i < Kal::MAX_CLUES + 1; ++$i) {
        $clues[] = ClueMother::create();
    }

    expect(fn () => KalMother::create(
        clues: CluesMother::of(...$clues),
        endsOn: DateTime::create('2026-12-01 00:00:00'),
    ))->toThrow(KalException::class, 'A kal cannot hold more than 24 clues.');
});
