<?php

declare(strict_types=1);

use App\User\Domain\ExternalId;
use App\User\Domain\UserId;
use Tests\User\Domain\Mother\AuthenticatedUserMother;
use Tests\User\Domain\Mother\ExternalIdMother;
use Tests\User\Domain\Mother\UserIdMother;

it('exposes the internal ULID as the identity the domain uses', function (): void {
    $id = UserIdMother::random();

    $user = AuthenticatedUserMother::create(id: $id);

    expect($user->id)->toBeInstanceOf(UserId::class)
        ->and($user->id->value())->toBe($id->value());
});

it('keeps the auth provider id apart from the domain identity', function (): void {
    $externalId = ExternalIdMother::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479');

    $user = AuthenticatedUserMother::create(externalId: $externalId);

    expect($user->externalId)->toBeInstanceOf(ExternalId::class)
        ->and($user->externalId->value())->toBe('f47ac10b-58cc-4372-a567-0e02b2c3d479')
        ->and($user->id->value())->not->toBe($user->externalId->value());
});
