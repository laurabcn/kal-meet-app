<?php

declare(strict_types=1);

use App\Shared\Domain\Exception\InvalidArgumentException;
use Tests\Unit\User\Domain\Mother\ExternalIdMother;

it('keeps the auth provider id verbatim', function (): void {
    $uuid = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';

    expect(ExternalIdMother::fromString($uuid)->value())->toBe($uuid);
});

it('accepts an opaque id that is not a uuid, so service tokens stay possible', function (): void {
    expect(ExternalIdMother::fromString('service-account:mcp-yarns')->value())->toBe('service-account:mcp-yarns');
});

it('considers two external ids with the same value as equal', function (): void {
    expect(ExternalIdMother::fromString('sub-1')->equals(ExternalIdMother::fromString('sub-1')))->toBeTrue();
});

it('considers two external ids with different values as not equal', function (): void {
    expect(ExternalIdMother::fromString('sub-1')->equals(ExternalIdMother::fromString('sub-2')))->toBeFalse();
});

it('rejects an empty auth provider id', function (string $value): void {
    ExternalIdMother::fromString($value);
})->with([
    'empty' => '',
    'whitespace only' => '   ',
])->throws(InvalidArgumentException::class);
