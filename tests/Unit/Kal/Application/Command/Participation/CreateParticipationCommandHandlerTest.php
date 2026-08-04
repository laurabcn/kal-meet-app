<?php

declare(strict_types=1);

use App\Kal\Application\Command\Participation\CreateParticipationCommand;
use App\Kal\Application\Command\Participation\CreateParticipationCommandHandler;
use App\Kal\Domain\Exception\KalAlreadyMemberException;
use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Exception\KalNotFoundException;
use App\Kal\Domain\Participation;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\UlidValue;
use Tests\Unit\Kal\Domain\Mother\KalMother;
use Tests\Unit\Kal\Infrastructure\Persistence\InMemoryKalRepository;
use Tests\Unit\Kal\Infrastructure\Persistence\InMemoryParticipationRepository;
use Tests\Unit\Shared\Infrastructure\Symfony\Security\StubTokenHandler;

beforeEach(function (): void {
    $this->kalRepository = new InMemoryKalRepository();
    $this->participationRepository = new InMemoryParticipationRepository();
    $this->handler = new CreateParticipationCommandHandler(
        $this->kalRepository,
        $this->participationRepository,
    );
});

it('persists a participation for a non-organizer with a matching invite token', function (): void {
    $kal = KalMother::create();
    $this->kalRepository->create($kal);
    $memberId = StubTokenHandler::USER_ID;

    ($this->handler)(new CreateParticipationCommand(
        $kal->id->value(),
        $kal->inviteToken->value(),
        $memberId,
    ));

    expect($this->participationRepository->all())->toHaveCount(1)
        ->and($this->participationRepository->all()[0]->kalId->value())->toBe($kal->id->value())
        ->and($this->participationRepository->all()[0]->userId->value())->toBe($memberId);
});

it('rejects the organizer as already a member without writing a participation', function (): void {
    $organizerId = UlidValue::create(StubTokenHandler::USER_ID);
    $kal = KalMother::create(organizerId: $organizerId);
    $this->kalRepository->create($kal);

    expect(fn () => ($this->handler)(new CreateParticipationCommand(
        $kal->id->value(),
        $kal->inviteToken->value(),
        $organizerId->value(),
    )))->toThrow(KalAlreadyMemberException::class, 'User is already a member of this kal.');

    expect($this->participationRepository->all())->toBeEmpty();
});

it('rejects a duplicate participation as already a member', function (): void {
    $kal = KalMother::create();
    $memberId = UlidValue::create(StubTokenHandler::USER_ID);
    $this->kalRepository->create($kal);
    $this->participationRepository->create(Participation::create(
        UlidValue::generate(),
        $kal->id,
        $memberId,
    ));

    expect(fn () => ($this->handler)(new CreateParticipationCommand(
        $kal->id->value(),
        $kal->inviteToken->value(),
        $memberId->value(),
    )))->toThrow(KalAlreadyMemberException::class, 'User is already a member of this kal.');

    expect($this->participationRepository->all())->toHaveCount(1);
});

it('rejects a missing kal as not found', function (): void {
    expect(fn () => ($this->handler)(new CreateParticipationCommand(
        '01J5M6XQBR4GTYHN8KZXP0F1W9',
        'deadbeefdeadbeefdeadbeefdeadbeef',
        StubTokenHandler::USER_ID,
    )))->toThrow(KalNotFoundException::class, 'Kal not found.');
});

it('rejects a soft-deleted kal as not found', function (): void {
    $kal = KalMother::create();
    $this->kalRepository->create($kal);
    $this->kalRepository->softDelete($kal->id->value());

    expect(fn () => ($this->handler)(new CreateParticipationCommand(
        $kal->id->value(),
        $kal->inviteToken->value(),
        StubTokenHandler::USER_ID,
    )))->toThrow(KalNotFoundException::class, 'Kal not found.');
});

it('rejects a mismatched invite token as not found', function (): void {
    $kal = KalMother::create();
    $this->kalRepository->create($kal);

    expect(fn () => ($this->handler)(new CreateParticipationCommand(
        $kal->id->value(),
        'deadbeefdeadbeefdeadbeefdeadbeef',
        StubTokenHandler::USER_ID,
    )))->toThrow(KalNotFoundException::class, 'Kal not found.');

    expect($this->participationRepository->all())->toBeEmpty();
});

it('rejects an empty invite token as a domain error', function (): void {
    $kal = KalMother::create();
    $this->kalRepository->create($kal);

    expect(fn () => ($this->handler)(new CreateParticipationCommand(
        $kal->id->value(),
        '',
        StubTokenHandler::USER_ID,
    )))->toThrow(KalException::class);
});

it('rejects a kal id that is not a ulid', function (): void {
    expect(fn () => ($this->handler)(new CreateParticipationCommand(
        'not-a-ulid',
        'deadbeefdeadbeefdeadbeefdeadbeef',
        StubTokenHandler::USER_ID,
    )))->toThrow(InvalidArgumentException::class);
});
