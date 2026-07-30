<?php

declare(strict_types=1);

use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\InviteToken;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use App\Shared\Domain\ValueObject\UlidValue;
use Tests\Kal\Domain\Mother\ClueMother;
use Tests\Kal\Domain\Mother\CluesMother;
use Tests\Kal\Domain\Mother\FileMother;
use Tests\Kal\Domain\Mother\FilesMother;
use Tests\Kal\Domain\Mother\KalMother;
use Tests\Kal\Domain\Mother\LocalesMother;
use Tests\Kal\Domain\Mother\MeetingMother;
use Tests\Kal\Domain\Mother\MeetingsMother;
use Tests\Shared\Domain\ValueObject\Mother\LocaleMother;

it('creates a kal with only the required fields', function (): void {
    $organizerId = UlidValue::generate();
    $name = new NonEmptyStringValue('Summer Shawl KAL');
    $startsOn = DateTime::create('2026-08-01 00:00:00');

    $kal = KalMother::create(organizerId: $organizerId, name: $name, startsOn: $startsOn);

    expect($kal->organizerId->equals($organizerId))->toBeTrue()
        ->and($kal->name->equals($name))->toBeTrue()
        ->and($kal->startsOn->equals($startsOn))->toBeTrue()
        ->and($kal->description)->toBeNull()
        ->and($kal->endsOn)->toBeNull()
        ->and($kal->coverPath)->toBeNull()
        ->and($kal->clues->all())->toBeEmpty()
        ->and($kal->meetings->all())->toBeEmpty()
        ->and($kal->id->value())->not->toBeEmpty()
        ->and($kal->createdAt)->toBeInstanceOf(DateTime::class)
        ->and($kal->updatedAt)->toBeInstanceOf(DateTime::class);
});

it('creates a kal with all optional fields populated', function (): void {
    $description = new NonEmptyStringValue('A knit-along for the summer');
    $endsOn = DateTime::create('2026-09-01 00:00:00');
    $coverPath = 'kals/summer-shawl/portada.webp';

    $kal = KalMother::create(description: $description, endsOn: $endsOn, coverPath: $coverPath);

    expect($kal->description)->not->toBeNull()
        ->and($kal->description?->equals($description))->toBeTrue()
        ->and($kal->endsOn)->not->toBeNull()
        ->and($kal->endsOn?->equals($endsOn))->toBeTrue()
        ->and($kal->coverPath)->toBe($coverPath);
});

it('exposes the enabled locales of the kal', function (): void {
    $kal = KalMother::create(locales: LocalesMother::catalanAndSpanish());

    expect($kal->locales->contains(LocaleMother::catalan()))->toBeTrue()
        ->and($kal->locales->contains(LocaleMother::spanish()))->toBeTrue()
        ->and($kal->locales->contains(LocaleMother::english()))->toBeFalse();
});

it('creates a kal with clues that are all within range', function (): void {
    $clue = ClueMother::create(
        startsOn: DateTime::create('2026-08-01 00:00:00'),
        endsOn: DateTime::create('2026-08-08 00:00:00'),
    );

    $kal = KalMother::create(
        clues: CluesMother::of($clue),
        endsOn: DateTime::create('2026-09-01 00:00:00'),
    );

    expect($kal->clues->all())->toHaveCount(1)
        ->and($kal->clues->all()[0]->id->equals($clue->id))->toBeTrue();
});

it('adds a valid clue to an already created kal', function (): void {
    $kal = KalMother::create();
    $clue = ClueMother::create();

    $kal->addClue($clue);

    expect($kal->clues->all())->toHaveCount(1)
        ->and($kal->clues->all()[0]->id->equals($clue->id))->toBeTrue();
});

it('throws when ends on is before starts on', function (): void {
    KalMother::create(
        startsOn: DateTime::create('2026-08-01 00:00:00'),
        endsOn: DateTime::create('2026-07-31 00:00:00'),
    );
})->throws(KalException::class, 'kal_invalid_date_range');

it('throws when ends on equals starts on', function (): void {
    $startsOn = DateTime::create('2026-08-01 00:00:00');

    KalMother::create(startsOn: $startsOn, endsOn: $startsOn);
})->throws(KalException::class, 'kal_invalid_date_range');

it('throws and creates no kal when one of the initial clues falls outside the range', function (): void {
    $clueOutsideRange = ClueMother::create(
        startsOn: DateTime::create('2026-07-15 00:00:00'),
        endsOn: DateTime::create('2026-07-20 00:00:00'),
    );

    KalMother::create(clues: CluesMother::of($clueOutsideRange));
})->throws(KalException::class, 'kal_clue_outside_range');

it('does not add a clue that starts before the kal', function (): void {
    $kal = KalMother::create();
    $clueBeforeStart = ClueMother::create(
        startsOn: DateTime::create('2026-07-15 00:00:00'),
        endsOn: DateTime::create('2026-07-20 00:00:00'),
    );

    expect(fn () => $kal->addClue($clueBeforeStart))
        ->toThrow(KalException::class, 'kal_clue_outside_range');
    expect($kal->clues->all())->toBeEmpty();
});

it('does not add a clue that ends after a bounded kal', function (): void {
    $kal = KalMother::create(endsOn: DateTime::create('2026-09-01 00:00:00'));
    $clueAfterEnd = ClueMother::create(
        startsOn: DateTime::create('2026-08-15 00:00:00'),
        endsOn: DateTime::create('2026-09-15 00:00:00'),
    );

    expect(fn () => $kal->addClue($clueAfterEnd))
        ->toThrow(KalException::class, 'kal_clue_outside_range');
    expect($kal->clues->all())->toBeEmpty();
});

it('only checks the lower bound when the kal has no ends on', function (): void {
    $kal = KalMother::create();
    $farFutureClue = ClueMother::create(
        startsOn: DateTime::create('2026-08-01 00:00:00'),
        endsOn: DateTime::create('2030-01-01 00:00:00'),
    );

    $kal->addClue($farFutureClue);

    expect($kal->clues->all())->toHaveCount(1);
});

it('accepts a clue whose starts on exactly matches the kal starts on', function (): void {
    $startsOn = DateTime::create('2026-08-01 00:00:00');
    $kal = KalMother::create(startsOn: $startsOn);
    $clueAtStart = ClueMother::create(
        startsOn: $startsOn,
        endsOn: DateTime::create('2026-08-08 00:00:00'),
    );

    $kal->addClue($clueAtStart);

    expect($kal->clues->all())->toHaveCount(1);
});

it('accepts a clue whose ends on exactly matches the kal ends on', function (): void {
    $endsOn = DateTime::create('2026-09-01 00:00:00');
    $kal = KalMother::create(endsOn: $endsOn);
    $clueAtEnd = ClueMother::create(
        startsOn: DateTime::create('2026-08-15 00:00:00'),
        endsOn: $endsOn,
    );

    $kal->addClue($clueAtEnd);

    expect($kal->clues->all())->toHaveCount(1);
});

it('creates a kal when a file uses one of the enabled locales', function (): void {
    $kal = KalMother::create(
        locales: LocalesMother::catalanAndSpanish(),
        files: FilesMother::withLocale(LocaleMother::spanish()),
    );

    expect($kal->locales->contains(LocaleMother::spanish()))->toBeTrue();
});

it('throws and creates no kal when a kal file uses a locale that is not enabled', function (): void {
    KalMother::create(
        locales: LocalesMother::catalanAndSpanish(),
        files: FilesMother::withLocale(LocaleMother::english()),
    );
})->throws(KalException::class, 'kal_file_locale_not_enabled');

it('throws and creates no kal when an initial clue file uses a locale that is not enabled', function (): void {
    $clueInEnglish = ClueMother::create(file: FileMother::withLocale(LocaleMother::english()));

    KalMother::create(
        locales: LocalesMother::catalanAndSpanish(),
        clues: CluesMother::of($clueInEnglish),
    );
})->throws(KalException::class, 'kal_file_locale_not_enabled');

it('throws and creates no kal when an initial clue uses a locale that is not enabled', function (): void {
    $clueInEnglish = ClueMother::create(locale: LocaleMother::english());

    KalMother::create(
        locales: LocalesMother::catalanAndSpanish(),
        clues: CluesMother::of($clueInEnglish),
    );
})->throws(KalException::class, 'kal_clue_locale_not_enabled');

it('does not add a clue whose locale is not enabled', function (): void {
    $kal = KalMother::create(locales: LocalesMother::catalanAndSpanish());
    $clueInEnglish = ClueMother::create(locale: LocaleMother::english());

    expect(fn () => $kal->addClue($clueInEnglish))
        ->toThrow(KalException::class, 'kal_clue_locale_not_enabled');
    expect($kal->clues->all())->toBeEmpty();
});

it('adds a clue whose file uses an enabled locale', function (): void {
    $kal = KalMother::create(locales: LocalesMother::catalanAndSpanish());
    $clueInCatalan = ClueMother::create(file: FileMother::withLocale(LocaleMother::catalan()));

    $kal->addClue($clueInCatalan);

    expect($kal->clues->all())->toHaveCount(1);
});

it('does not add a clue whose file uses a locale that is not enabled', function (): void {
    $kal = KalMother::create(locales: LocalesMother::catalanAndSpanish());
    $clueInEnglish = ClueMother::create(file: FileMother::withLocale(LocaleMother::english()));

    expect(fn () => $kal->addClue($clueInEnglish))
        ->toThrow(KalException::class, 'kal_file_locale_not_enabled');
    expect($kal->clues->all())->toBeEmpty();
});

// --- inviteToken ---

it('always generates an invite token at creation', function (): void {
    $kal = KalMother::create();

    expect($kal->inviteToken)->toBeInstanceOf(InviteToken::class)
        ->and($kal->inviteToken->value())->not->toBeEmpty()
        ->and(strlen($kal->inviteToken->value()))->toBe(32);
});

it('generates a unique invite token for each kal', function (): void {
    $kal1 = KalMother::create();
    $kal2 = KalMother::create();

    expect($kal1->inviteToken->equals($kal2->inviteToken))->toBeFalse();
});

it('throws when reconstructing an invite token from an empty string', function (): void {
    InviteToken::fromString('');
})->throws(KalException::class, 'kal_empty_invite_token');

it('reconstructs an invite token from a valid string', function (): void {
    $token = InviteToken::fromString('abc123def456');

    expect($token->value())->toBe('abc123def456');
});

// --- meetings ---

it('creates a kal with no meetings by default', function (): void {
    $kal = KalMother::create();

    expect($kal->meetings->all())->toBeEmpty();
});

it('creates a kal with initial meetings', function (): void {
    $meeting = MeetingMother::create();

    $kal = KalMother::create(meetings: MeetingsMother::of($meeting));

    expect($kal->meetings->all())->toHaveCount(1)
        ->and($kal->meetings->all()[0]->id->equals($meeting->id))->toBeTrue();
});

it('adds a meeting to an already created kal', function (): void {
    $kal = KalMother::create();
    $meeting = MeetingMother::create();

    $kal->addMeeting($meeting);

    expect($kal->meetings->all())->toHaveCount(1)
        ->and($kal->meetings->all()[0]->id->equals($meeting->id))->toBeTrue();
});

it('adds multiple meetings to a kal', function (): void {
    $kal = KalMother::create();
    $meeting1 = MeetingMother::create(title: new NonEmptyStringValue('Session 1'));
    $meeting2 = MeetingMother::create(title: new NonEmptyStringValue('Session 2'));

    $kal->addMeeting($meeting1);
    $kal->addMeeting($meeting2);

    expect($kal->meetings->all())->toHaveCount(2);
});
