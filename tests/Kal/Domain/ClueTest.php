<?php

declare(strict_types=1);

use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\File;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use Tests\Kal\Domain\Mother\ClueMother;
use Tests\Kal\Domain\Mother\FileMother;
use Tests\Shared\Domain\ValueObject\Mother\LocaleMother;

it('creates a clue when ends on is after starts on', function (): void {
    $startsOn = DateTime::create('2026-08-01 00:00:00');
    $endsOn = DateTime::create('2026-08-08 00:00:00');

    $clue = ClueMother::create(startsOn: $startsOn, endsOn: $endsOn);

    expect($clue->startsOn()->equals($startsOn))->toBeTrue()
        ->and($clue->endsOn()->equals($endsOn))->toBeTrue()
        ->and($clue->description())->toBeNull()
        ->and($clue->file())->toBeInstanceOf(File::class)
        ->and($clue->id()->value())->not->toBeEmpty()
        ->and($clue->updatedAt())->toBeInstanceOf(DateTime::class);
});

it('creates a clue carrying the file it was given', function (): void {
    $file = FileMother::withLocale(LocaleMother::spanish());

    $clue = ClueMother::create(file: $file);

    expect($clue->file()->locale->equals(LocaleMother::spanish()))->toBeTrue();
});

it('creates a clue with the name it was given', function (): void {
    $clue = ClueMother::create(name: 'Round 1');

    expect($clue->name()->equals(new NonEmptyStringValue('Round 1')))->toBeTrue();
});

it('throws when ends on is before starts on', function (): void {
    ClueMother::create(
        startsOn: DateTime::create('2026-08-01 00:00:00'),
        endsOn: DateTime::create('2026-07-31 00:00:00'),
    );
})->throws(KalException::class, 'kal_invalid_date_range');

it('throws when ends on equals starts on', function (): void {
    $startsOn = DateTime::create('2026-08-01 00:00:00');

    ClueMother::create(startsOn: $startsOn, endsOn: $startsOn);
})->throws(KalException::class, 'kal_invalid_date_range');
