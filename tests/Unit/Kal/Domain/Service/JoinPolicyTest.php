<?php

declare(strict_types=1);

use App\Kal\Domain\Exception\KalAlreadyMemberException;
use App\Kal\Domain\Exception\KalStateException;
use App\Kal\Domain\Participation;
use App\Kal\Domain\Service\JoinPolicy;
use App\Shared\Domain\ValueObject\UlidValue;
use Tests\Unit\Kal\Domain\Mother\KalMother;
use Tests\Unit\Kal\Infrastructure\Persistence\InMemoryParticipationRepository;

beforeEach(function (): void {
    $this->participations = new InMemoryParticipationRepository();
    $this->policy = new JoinPolicy($this->participations);
});

it('lets a stranger join', function (): void {
    $kal = KalMother::create();

    expect(fn () => $this->policy->ensureCanJoin($kal, UlidValue::generate()))
        ->not->toThrow(KalAlreadyMemberException::class);
});

it('rejects the organizer, who is a member by role', function (): void {
    $organizerId = UlidValue::generate();
    $kal = KalMother::create(organizerId: $organizerId);

    expect(fn () => $this->policy->ensureCanJoin($kal, $organizerId))
        ->toThrow(KalAlreadyMemberException::class, 'User is already a member of this kal.');
});

it('rejects someone who already has a participation', function (): void {
    $kal = KalMother::create();
    $memberId = UlidValue::generate();
    $this->participations->create(Participation::create(UlidValue::generate(), $kal->id, $memberId));

    expect(fn () => $this->policy->ensureCanJoin($kal, $memberId))
        ->toThrow(KalAlreadyMemberException::class, 'User is already a member of this kal.');
});

// La participació d'un altre KAL no compta: si l'`exists()` ignorés el kalId,
// apuntar-se al segon KAL donaria un 409 que ningú entendria.
it('ignores a participation in a different kal', function (): void {
    $kal = KalMother::create();
    $memberId = UlidValue::generate();
    $this->participations->create(
        Participation::create(UlidValue::generate(), UlidValue::generate(), $memberId),
    );

    expect(fn () => $this->policy->ensureCanJoin($kal, $memberId))
        ->not->toThrow(KalAlreadyMemberException::class);
});

it('propagates a persistence failure from the participation lookup', function (): void {
    $kal = KalMother::create();
    $this->participations->failWith(
        KalStateException::persistenceFailed(new RuntimeException('connection lost')),
    );

    expect(fn () => $this->policy->ensureCanJoin($kal, UlidValue::generate()))
        ->toThrow(KalStateException::class, 'Failed to persist the kal.');
});
