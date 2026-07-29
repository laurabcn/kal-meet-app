<?php

declare(strict_types=1);

use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\File;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use Tests\Kal\Domain\Mother\ClueMother;
use Tests\Kal\Domain\Mother\FileMother;
use Tests\Kal\Domain\Mother\MeetingMother;
use Tests\Shared\Domain\ValueObject\Mother\LocaleMother;

it('creates a clue when ends on is after starts on', function (): void {
    $startsOn = DateTime::create('2026-08-01 00:00:00');
    $endsOn = DateTime::create('2026-08-08 00:00:00');

    $clue = ClueMother::create(startsOn: $startsOn, endsOn: $endsOn);

    expect($clue->startsOn->equals($startsOn))->toBeTrue()
        ->and($clue->endsOn->equals($endsOn))->toBeTrue()
        ->and($clue->description)->toBeNull()
        ->and($clue->file)->toBeInstanceOf(File::class)
        ->and($clue->id->value())->not->toBeEmpty()
        ->and($clue->updatedAt)->toBeInstanceOf(DateTime::class);
});

it('creates a clue carrying the file it was given', function (): void {
    $file = FileMother::withLocale(LocaleMother::spanish());

    $clue = ClueMother::create(file: $file);

    expect($clue->file->locale->equals(LocaleMother::spanish()))->toBeTrue();
});

it('creates a clue with the name it was given', function (): void {
    $clue = ClueMother::create(name: 'Round 1');

    expect($clue->name->equals(new NonEmptyStringValue('Round 1')))->toBeTrue();
});

it('creates a clue carrying the meeting it was given', function (): void {
    $meeting = MeetingMother::create(
        scheduledAt: DateTime::create('2026-08-03 18:00:00'),
        title: new NonEmptyStringValue('Round 1 live session'),
    );

    $clue = ClueMother::create(meeting: $meeting);

    expect($clue->meeting->id->equals($meeting->id))->toBeTrue()
        ->and($clue->meeting->title->value())->toBe('Round 1 live session');
});

it('creates a clue with the locale it was given', function (): void {
    $clue = ClueMother::create(locale: LocaleMother::spanish());

    expect($clue->locale->equals(LocaleMother::spanish()))->toBeTrue();
});

it('falls back to the locale of its file when none is given', function (): void {
    $clue = ClueMother::create(file: FileMother::withLocale(LocaleMother::spanish()));

    expect($clue->locale->equals(LocaleMother::spanish()))->toBeTrue();
});

it('throws when the meeting is scheduled before the clue starts', function (): void {
    ClueMother::create(
        startsOn: DateTime::create('2026-08-01 00:00:00'),
        endsOn: DateTime::create('2026-08-08 00:00:00'),
        meeting: MeetingMother::create(scheduledAt: DateTime::create('2026-07-30 18:00:00')),
    );
})->throws(KalException::class, 'kal_meeting_outside_clue_range');

it('throws when the meeting is scheduled after the clue ends', function (): void {
    ClueMother::create(
        startsOn: DateTime::create('2026-08-01 00:00:00'),
        endsOn: DateTime::create('2026-08-08 00:00:00'),
        meeting: MeetingMother::create(scheduledAt: DateTime::create('2026-08-09 18:00:00')),
    );
})->throws(KalException::class, 'kal_meeting_outside_clue_range');

it('accepts a meeting scheduled exactly when the clue ends', function (): void {
    $endsOn = DateTime::create('2026-08-08 00:00:00');

    $clue = ClueMother::create(
        startsOn: DateTime::create('2026-08-01 00:00:00'),
        endsOn: $endsOn,
        meeting: MeetingMother::create(scheduledAt: $endsOn),
    );

    expect($clue->meeting->scheduledAt->equals($endsOn))->toBeTrue();
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
