<?php

declare(strict_types=1);

use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\UlidValue;
use App\User\Domain\UserId;
use Tests\User\Domain\Mother\UserIdMother;

it('creates a user id from a valid ULID', function (): void {
    $ulid = UlidValue::generate()->value();

    expect(UserIdMother::fromString($ulid)->value())->toBe($ulid);
});

it('is a distinct type, so it cannot be confused with any other ULID', function (): void {
    $id = UserIdMother::random();

    expect($id)->toBeInstanceOf(UserId::class)
        ->and($id)->toBeInstanceOf(UlidValue::class);
});

it('considers two user ids with the same value as equal', function (): void {
    $ulid = UlidValue::generate()->value();

    expect(UserIdMother::fromString($ulid)->equals(UserIdMother::fromString($ulid)))->toBeTrue();
});

it('considers two user ids with different values as not equal', function (): void {
    expect(UserIdMother::random()->equals(UserIdMother::random()))->toBeFalse();
});

it('rejects anything that is not a ULID, including a Supabase auth uuid', function (string $value): void {
    UserIdMother::fromString($value);
})->with([
    'empty' => '',
    'whitespace only' => '   ',
    'a supabase auth uuid' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
    'too short' => '01ARZ3NDEKTSV4RRFFQ69G5FA',
    'too long' => '01ARZ3NDEKTSV4RRFFQ69G5FAVV',
    'invalid crockford character' => '01ARZ3NDEKTSV4RRFFQ69G5FAU',
])->throws(InvalidArgumentException::class);
