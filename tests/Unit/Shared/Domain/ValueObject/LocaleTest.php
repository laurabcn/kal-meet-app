<?php

declare(strict_types=1);

use App\Shared\Domain\Exception\InvalidArgumentException;
use Tests\Unit\Shared\Domain\ValueObject\Mother\LocaleMother;

it('creates a locale from a valid ISO 639-1 language code', function (string $code): void {
    $locale = LocaleMother::fromString($code);

    expect($locale->value())->toBe($code);
})->with(['ca', 'es', 'en', 'fr', 'de', 'it', 'pt', 'eu', 'gl', 'zh', 'ja']);

it('normalizes the casing to lowercase', function (): void {
    expect(LocaleMother::fromString('CA')->value())->toBe('ca')
        ->and(LocaleMother::fromString('Es')->value())->toBe('es');
});

it('trims surrounding whitespace before validating', function (): void {
    expect(LocaleMother::fromString('  fr  ')->value())->toBe('fr');
});

it('considers two locales with the same normalized value as equal', function (): void {
    expect(LocaleMother::fromString('ca')->equals(LocaleMother::fromString('CA')))->toBeTrue();
});

it('considers two locales with different values as not equal', function (): void {
    expect(LocaleMother::catalan()->equals(LocaleMother::spanish()))->toBeFalse();
});

it('throws when the code is not a real language', function (string $code): void {
    LocaleMother::fromString($code);
})->with([
    'empty' => '',
    'whitespace only' => '   ',
    'well-formed but unassigned' => 'zz',
    'made up' => 'xyz',
    'a word' => 'hola',
    'too long' => 'english',
    'too short' => 'e',
    'a number' => '1',
    'language with region' => 'ca_ES',
])->throws(InvalidArgumentException::class);
