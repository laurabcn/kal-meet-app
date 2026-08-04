<?php

declare(strict_types=1);

use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\DateTime;

it('creates a date time from a value without an explicit time zone', function (): void {
    $dateTime = DateTime::create('2026-07-24 10:00:00');

    expect($dateTime->value())->toBe('2026-07-24 10:00:00');
});

it('creates a date time interpreting a wall clock string in the given time zone', function (): void {
    $dateTime = DateTime::create('2026-07-24 10:00:00', 'Europe/Madrid');

    // Europe/Madrid is UTC+2 in July (CEST), so 10:00 local is 08:00 UTC.
    expect($dateTime->value())->toBe('2026-07-24 08:00:00');
});

it('throws when the time zone identifier is invalid', function (): void {
    DateTime::create('2026-07-24 10:00:00', 'Not/A_Zone');
})->throws(InvalidArgumentException::class);

it('throws when the value does not match a parseable date time format', function (): void {
    DateTime::create('not-a-date');
})->throws(InvalidArgumentException::class);

it('considers two instants built from different time zones as equal when they represent the same instant', function (): void {
    $utc = DateTime::create('2026-07-24 08:00:00');
    $madrid = DateTime::create('2026-07-24 10:00:00', 'Europe/Madrid');

    expect($utc->equals($madrid))->toBeTrue();
});

it('reports is after based on the absolute instant, not the time zone', function (): void {
    $earlier = DateTime::create('2026-07-24 08:00:00');
    $later = DateTime::create('2026-07-24 09:00:00');

    expect($later->isAfter($earlier))->toBeTrue()
        ->and($earlier->isAfter($later))->toBeFalse()
        ->and($earlier->isAfter($earlier))->toBeFalse();
});

it('reports is before based on the absolute instant, not the time zone', function (): void {
    $earlier = DateTime::create('2026-07-24 08:00:00');
    $later = DateTime::create('2026-07-24 09:00:00');

    expect($earlier->isBefore($later))->toBeTrue()
        ->and($later->isBefore($earlier))->toBeFalse()
        ->and($earlier->isBefore($earlier))->toBeFalse();
});

it('reports is after or equal inclusively', function (): void {
    $moment = DateTime::create('2026-07-24 08:00:00');
    $same = DateTime::create('2026-07-24 08:00:00');
    $later = DateTime::create('2026-07-24 09:00:00');

    expect($moment->isAfterOrEqual($same))->toBeTrue()
        ->and($later->isAfterOrEqual($moment))->toBeTrue()
        ->and($moment->isAfterOrEqual($later))->toBeFalse();
});

it('reports is before or equal inclusively', function (): void {
    $moment = DateTime::create('2026-07-24 08:00:00');
    $same = DateTime::create('2026-07-24 08:00:00');
    $later = DateTime::create('2026-07-24 09:00:00');

    expect($moment->isBeforeOrEqual($same))->toBeTrue()
        ->and($moment->isBeforeOrEqual($later))->toBeTrue()
        ->and($later->isBeforeOrEqual($moment))->toBeFalse();
});

it('considers two different instants as not equal', function (): void {
    $first = DateTime::create('2026-07-24 08:00:00');
    $second = DateTime::create('2026-07-24 09:00:00');

    expect($first->equals($second))->toBeFalse();
});
