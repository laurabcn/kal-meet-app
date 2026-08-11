<?php

declare(strict_types=1);

use App\Kal\Application\Query\ListKals\ListKalsQuery;
use App\Kal\Application\Query\ListKals\ListKalsQueryHandler;
use App\Kal\Domain\Exception\KalStateException;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use App\Shared\Domain\ValueObject\UlidValue;
use Tests\Unit\Kal\Domain\Mother\KalMother;
use Tests\Unit\Kal\Infrastructure\Persistence\InMemoryKalRepository;

beforeEach(function (): void {
    $this->repository = new InMemoryKalRepository();
    $this->handler = new ListKalsQueryHandler($this->repository);
});

it('returns the summary of every kal of the organizer', function (): void {
    $kal = KalMother::create(
        organizerId: UlidValue::create('01J5M6XQBR4GTYHN8KZXP0F1W2'),
        name: NonEmptyStringValue::create('Mitons'),
        description: NonEmptyStringValue::create('Un KAL de mitons'),
        coverPath: 'cover.webp',
    );
    $this->repository->create($kal);

    $result = ($this->handler)(new ListKalsQuery('01J5M6XQBR4GTYHN8KZXP0F1W2'))->result();

    expect($result)->toHaveCount(1)
        ->and($result[0])->toBe([
            'id' => $kal->id->value(),
            'name' => 'Mitons',
            'description' => 'Un KAL de mitons',
            'startsOn' => $kal->startsOn->value(),
            'endsOn' => $kal->endsOn?->value(),
            'coverPath' => 'cover.webp',
        ]);
});

it('never exposes the invite token in the list', function (): void {
    $this->repository->create(KalMother::create(organizerId: UlidValue::create('01J5M6XQBR4GTYHN8KZXP0F1W2')));

    $result = ($this->handler)(new ListKalsQuery('01J5M6XQBR4GTYHN8KZXP0F1W2'))->result();

    expect($result[0])->not->toHaveKey('inviteToken');
});

it('returns an empty list for an organizer without kals', function (): void {
    expect(($this->handler)(new ListKalsQuery('01J5M6XQBR4GTYHN8KZXP0F1W2'))->result())->toBe([]);
});

it('leaves out the kals of other organizers', function (): void {
    $mine = KalMother::create(organizerId: UlidValue::create('01J5M6XQBR4GTYHN8KZXP0F1W2'));
    $theirs = KalMother::create(organizerId: UlidValue::create('01J5M6XQBR4GTYHN8KZXP0F1W9'));
    $this->repository->create($mine);
    $this->repository->create($theirs);

    $result = ($this->handler)(new ListKalsQuery('01J5M6XQBR4GTYHN8KZXP0F1W2'))->result();

    expect($result)->toHaveCount(1)
        ->and($result[0]['id'])->toBe($mine->id->value());
});

it('leaves out soft-deleted kals', function (): void {
    $kept = KalMother::create(organizerId: UlidValue::create('01J5M6XQBR4GTYHN8KZXP0F1W2'));
    $gone = KalMother::create(organizerId: UlidValue::create('01J5M6XQBR4GTYHN8KZXP0F1W2'));
    $this->repository->create($kept);
    $this->repository->create($gone);
    $this->repository->softDelete($gone->id->value());

    $result = ($this->handler)(new ListKalsQuery('01J5M6XQBR4GTYHN8KZXP0F1W2'))->result();

    expect($result)->toHaveCount(1)
        ->and($result[0]['id'])->toBe($kept->id->value());
});

it('puts the kal that starts last first', function (): void {
    $older = KalMother::create(
        organizerId: UlidValue::create('01J5M6XQBR4GTYHN8KZXP0F1W2'),
        startsOn: DateTime::create('2026-01-01 00:00:00'),
        endsOn: DateTime::create('2026-02-01 00:00:00'),
    );
    $newer = KalMother::create(
        organizerId: UlidValue::create('01J5M6XQBR4GTYHN8KZXP0F1W2'),
        startsOn: DateTime::create('2026-09-01 00:00:00'),
        endsOn: DateTime::create('2026-10-01 00:00:00'),
    );
    $this->repository->create($older);
    $this->repository->create($newer);

    $result = ($this->handler)(new ListKalsQuery('01J5M6XQBR4GTYHN8KZXP0F1W2'))->result();

    expect(array_column($result, 'id'))->toBe([$newer->id->value(), $older->id->value()]);
});

it('fails when the organizer id is not a valid ulid', function (): void {
    expect(fn () => ($this->handler)(new ListKalsQuery('not-a-ulid')))
        ->toThrow(InvalidArgumentException::class);
});

it('lets a persistence failure surface instead of reporting an empty list', function (): void {
    $this->repository->create(KalMother::create(organizerId: UlidValue::create('01J5M6XQBR4GTYHN8KZXP0F1W2')));
    $this->repository->failWith(KalStateException::persistenceFailed(new RuntimeException('connection lost')));

    expect(fn () => ($this->handler)(new ListKalsQuery('01J5M6XQBR4GTYHN8KZXP0F1W2')))
        ->toThrow(KalStateException::class, 'Failed to persist the kal.');
});
