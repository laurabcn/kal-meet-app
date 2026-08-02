<?php

declare(strict_types=1);

namespace Tests\Shared\Domain\Security;

use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\Security\AuthenticatedUserIdentity;
use Symfony\Component\Uid\Ulid;

it('accepts a ULID id and a non-empty external id', function (): void {
    $id = (string) new Ulid();
    $identity = AuthenticatedUserIdentity::create($id, '550e8400-e29b-41d4-a716-446655440000');

    expect($identity->id)->toBe($id)
        ->and($identity->externalId)->toBe('550e8400-e29b-41d4-a716-446655440000');
});

it('rejects an empty id or external id', function (): void {
    $id = (string) new Ulid();

    expect(fn () => AuthenticatedUserIdentity::create('', 'ext'))
        ->toThrow(InvalidArgumentException::class, 'auth_identity_incomplete')
        ->and(fn () => AuthenticatedUserIdentity::create($id, ''))
        ->toThrow(InvalidArgumentException::class, 'auth_identity_incomplete');
});

it('rejects a non-ULID id so a Supabase uuid cannot cross the boundary as profiles.id', function (): void {
    expect(fn () => AuthenticatedUserIdentity::create(
        '550e8400-e29b-41d4-a716-446655440000',
        '550e8400-e29b-41d4-a716-446655440000',
    ))->toThrow(InvalidArgumentException::class, 'auth_identity_incomplete');
});
