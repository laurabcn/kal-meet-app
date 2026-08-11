<?php

declare(strict_types=1);

use App\Kal\Application\Command\Kal\DeleteKalCommand;
use App\Kal\Application\Command\Kal\DeleteKalCommandHandler;
use App\Kal\Domain\Exception\KalNotFoundException;
use App\Kal\Domain\Exception\KalStateException;
use App\Shared\Domain\ValueObject\UlidValue;
use Tests\Unit\Kal\Domain\Mother\KalMother;
use Tests\Unit\Kal\Infrastructure\Persistence\InMemoryKalRepository;

beforeEach(function (): void {
    $this->repository = new InMemoryKalRepository();
    $this->handler = new DeleteKalCommandHandler($this->repository);
});

it('soft-deletes the kal of its organizer', function (): void {
    $kal = KalMother::create(
        id: UlidValue::create('01J5M6XQBR4GTYHN8KZXP0F1W3'),
        organizerId: UlidValue::create('01J5M6XQBR4GTYHN8KZXP0F1W2'),
    );
    $this->repository->create($kal);

    ($this->handler)(new DeleteKalCommand(
        id: '01J5M6XQBR4GTYHN8KZXP0F1W3',
        organizerId: '01J5M6XQBR4GTYHN8KZXP0F1W2',
    ));

    expect($this->repository->isDeleted('01J5M6XQBR4GTYHN8KZXP0F1W3'))->toBeTrue();
});

it('leaves the kal unreadable once deleted', function (): void {
    $kal = KalMother::create(
        id: UlidValue::create('01J5M6XQBR4GTYHN8KZXP0F1W3'),
        organizerId: UlidValue::create('01J5M6XQBR4GTYHN8KZXP0F1W2'),
    );
    $this->repository->create($kal);

    ($this->handler)(new DeleteKalCommand(
        id: '01J5M6XQBR4GTYHN8KZXP0F1W3',
        organizerId: '01J5M6XQBR4GTYHN8KZXP0F1W2',
    ));

    expect(fn () => $this->repository->findById($kal->id, $kal->organizerId))
        ->toThrow(KalNotFoundException::class, 'Kal not found.');
});

it('throws kal_not_found when the kal does not exist', function (): void {
    expect(fn () => ($this->handler)(new DeleteKalCommand(
        id: '01J5M6XQBR4GTYHN8KZXP0F1W3',
        organizerId: '01J5M6XQBR4GTYHN8KZXP0F1W2',
    )))->toThrow(KalNotFoundException::class, 'Kal not found.');
});

it('throws kal_not_found when the caller is not the organizer', function (): void {
    $kal = KalMother::create(
        id: UlidValue::create('01J5M6XQBR4GTYHN8KZXP0F1W3'),
        organizerId: UlidValue::create('01J5M6XQBR4GTYHN8KZXP0F1W2'),
    );
    $this->repository->create($kal);

    expect(fn () => ($this->handler)(new DeleteKalCommand(
        id: '01J5M6XQBR4GTYHN8KZXP0F1W3',
        organizerId: '01J5M6XQBR4GTYHN8KZXP0F1W9',
    )))->toThrow(KalNotFoundException::class, 'Kal not found.');

    expect($this->repository->isDeleted('01J5M6XQBR4GTYHN8KZXP0F1W3'))->toBeFalse();
});

it('throws kal_not_found when the kal was already deleted', function (): void {
    $kal = KalMother::create(
        id: UlidValue::create('01J5M6XQBR4GTYHN8KZXP0F1W3'),
        organizerId: UlidValue::create('01J5M6XQBR4GTYHN8KZXP0F1W2'),
    );
    $this->repository->create($kal);
    $this->repository->softDelete('01J5M6XQBR4GTYHN8KZXP0F1W3');

    expect(fn () => ($this->handler)(new DeleteKalCommand(
        id: '01J5M6XQBR4GTYHN8KZXP0F1W3',
        organizerId: '01J5M6XQBR4GTYHN8KZXP0F1W2',
    )))->toThrow(KalNotFoundException::class, 'Kal not found.');
});

it('lets a persistence failure surface instead of reporting success', function (): void {
    $kal = KalMother::create(
        id: UlidValue::create('01J5M6XQBR4GTYHN8KZXP0F1W3'),
        organizerId: UlidValue::create('01J5M6XQBR4GTYHN8KZXP0F1W2'),
    );
    $this->repository->create($kal);
    $this->repository->failWith(KalStateException::persistenceFailed(new RuntimeException('connection lost')));

    expect(fn () => ($this->handler)(new DeleteKalCommand(
        id: '01J5M6XQBR4GTYHN8KZXP0F1W3',
        organizerId: '01J5M6XQBR4GTYHN8KZXP0F1W2',
    )))->toThrow(KalStateException::class, 'Failed to persist the kal.');
});
