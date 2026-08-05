<?php

declare(strict_types=1);

use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Locales;
use Tests\Unit\Kal\Domain\Mother\LocalesMother;
use Tests\Unit\Shared\Domain\ValueObject\Mother\LocaleMother;

it('creates a collection holding the given locales', function (): void {
    $locales = LocalesMother::catalanAndSpanish();

    expect($locales->all())->toHaveCount(2);
});

it('reports that it contains an enabled locale', function (): void {
    $locales = LocalesMother::of(LocaleMother::catalan(), LocaleMother::spanish());

    expect($locales->contains(LocaleMother::catalan()))->toBeTrue()
        ->and($locales->contains(LocaleMother::spanish()))->toBeTrue();
});

it('matches by normalized value regardless of casing', function (): void {
    $locales = LocalesMother::of(LocaleMother::catalan());

    expect($locales->contains(LocaleMother::fromString('CA')))->toBeTrue();
});

it('reports that it does not contain a locale that was not enabled', function (): void {
    $locales = LocalesMother::catalanOnly();

    expect($locales->contains(LocaleMother::english()))->toBeFalse();
});

it('deduplicates exact-match locales', function (): void {
    $locales = Locales::create([
        LocaleMother::catalan(),
        LocaleMother::catalan(),
        LocaleMother::spanish(),
    ]);

    expect($locales->all())->toHaveCount(2);
});

it('deduplicates case-variant locales', function (): void {
    $locales = Locales::create([
        LocaleMother::fromString('ca'),
        LocaleMother::fromString('CA'),
    ]);

    expect($locales->all())->toHaveCount(1)
        ->and($locales->all()[0]->value())->toBe('ca');
});

it('throws when created with no locales at all', function (): void {
    Locales::create([]);
})->throws(KalException::class, 'At least one locale must be enabled for the kal.');
