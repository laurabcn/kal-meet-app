<?php

declare(strict_types=1);

use App\Kal\Domain\Exception\KalAlreadyMemberException;
use App\Kal\Domain\Exception\KalStateException;
use App\Kal\Domain\Participation;
use App\Kal\Infrastructure\Repository\MySQL\Hydrator\KalHydrator;
use App\Kal\Infrastructure\Repository\MySQL\KalRepository;
use App\Kal\Infrastructure\Repository\MySQL\ParticipationRepository;
use App\Shared\Domain\ValueObject\UlidValue;
use App\Shared\Infrastructure\Repository\MySQLRepository;
use Psr\Log\NullLogger;
use Tests\Integration\Kal\Infrastructure\Persistence\SupabaseConnection;
use Tests\Unit\Kal\Domain\Mother\KalMother;

beforeEach(function (): void {
    SupabaseConnection::begin();

    $this->connection = SupabaseConnection::get();
    $mysql = new MySQLRepository($this->connection);
    $this->kalRepository = new KalRepository($mysql, new NullLogger(), new KalHydrator());
    $this->participationRepository = new ParticipationRepository($mysql);

    $this->organizerId = UlidValue::generate();
    $this->memberId = UlidValue::generate();
    SupabaseConnection::insertProfile($this->organizerId->value());
    SupabaseConnection::insertProfile($this->memberId->value());

    $this->kal = KalMother::create(organizerId: $this->organizerId);
    $this->kalRepository->create($this->kal);
});

afterEach(function (): void {
    SupabaseConnection::rollBack();
});

it('persists a participation row', function (): void {
    $participation = Participation::create(UlidValue::generate(), $this->kal->id, $this->memberId);

    $this->participationRepository->create($participation);

    $row = $this->connection->fetchAssociative(
        'SELECT * FROM participations WHERE id = :id',
        ['id' => $participation->id->value()],
    );

    expect($row['kal_id'])->toBe($this->kal->id->value())
        ->and($row['user_id'])->toBe($this->memberId->value())
        // El `created_at` que quedava null ja va costar un 500 en producció
        // (CLAUDE.md, «Errors ja comesos»): aquí es comprova que el default
        // de la columna hi escriu de veritat.
        ->and($row['joined_at'])->not->toBeNull()
        ->and($this->participationRepository->exists($this->kal->id, $this->memberId))->toBeTrue();
});

// `create()` ja no fa pre-check: el cas normal el talla l'`exists()` del
// handler, o sigui que arribar dues vegades a l'insert és exactament la cursa
// que ha d'aturar l'índex únic `participations_kal_user_unique`.
it('maps a duplicate participation to kal_already_member', function (): void {
    $this->participationRepository->create(
        Participation::create(UlidValue::generate(), $this->kal->id, $this->memberId),
    );

    expect(fn () => $this->participationRepository->create(
        Participation::create(UlidValue::generate(), $this->kal->id, $this->memberId),
    ))->toThrow(KalAlreadyMemberException::class, 'User is already a member of this kal.');
});

it('reports an unknown user as a persistence failure', function (): void {
    $participation = Participation::create(
        UlidValue::generate(),
        $this->kal->id,
        UlidValue::generate(), // cap perfil amb aquest id: viola participations_user_fk
    );

    expect(fn () => $this->participationRepository->create($participation))
        ->toThrow(KalStateException::class, 'Failed to persist the kal.');
});

it('reports exists as false when there is no participation', function (): void {
    expect($this->participationRepository->exists($this->kal->id, $this->memberId))->toBeFalse();
});
