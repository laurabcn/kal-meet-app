<?php

declare(strict_types=1);

use App\Kal\Domain\Repository\KalRepositoryInterface;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use App\Shared\Domain\ValueObject\UlidValue;
use Symfony\Component\HttpFoundation\Response;
use Tests\Unit\Kal\Domain\Mother\KalMother;
use Tests\Unit\Kal\Infrastructure\Persistence\InMemoryKalRepository;
use Tests\Unit\Shared\Infrastructure\Symfony\Security\StubTokenHandler;

it('answers 200 with the kals of the organizer inside a data envelope', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);

    $kal = KalMother::create(
        organizerId: UlidValue::create(StubTokenHandler::USER_ID),
        name: NonEmptyStringValue::create('Mitons'),
    );
    $repository->create($kal);

    $client->request('GET', '/kal', server: apiJsonHeaders());

    $payload = json_decode((string) $client->getResponse()->getContent(), true);

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($payload)->toHaveKey('data')
        ->and($payload['data'])->toHaveCount(1)
        ->and($payload['data'][0]['id'])->toBe($kal->id->value())
        ->and($payload['data'][0]['name'])->toBe('Mitons')
        ->and($payload['data'][0])->not->toHaveKey('inviteToken');
});

it('answers 200 with an empty list when the organizer has no kals', function (): void {
    $client = static::createClient();

    $client->request('GET', '/kal', server: apiJsonHeaders());

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($client->getResponse()->getContent())->toBe('{"data":[]}');
});

it('does not list the kals of another organizer', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);

    $repository->create(KalMother::create());

    $client->request('GET', '/kal', server: apiJsonHeaders());

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($client->getResponse()->getContent())->toBe('{"data":[]}');
});

it('drops a kal from the list once it is deleted', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);

    $kal = KalMother::create(organizerId: UlidValue::create(StubTokenHandler::USER_ID));
    $repository->create($kal);

    $client->request('DELETE', '/kal/'.$kal->id->value(), server: apiJsonHeaders());
    $client->request('GET', '/kal', server: apiJsonHeaders());

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($client->getResponse()->getContent())->toBe('{"data":[]}');
});

it('lists the kal that starts last first', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);

    $older = KalMother::create(
        organizerId: UlidValue::create(StubTokenHandler::USER_ID),
        startsOn: DateTime::create('2026-01-01 00:00:00'),
        endsOn: DateTime::create('2026-02-01 00:00:00'),
    );
    $newer = KalMother::create(
        organizerId: UlidValue::create(StubTokenHandler::USER_ID),
        startsOn: DateTime::create('2026-09-01 00:00:00'),
        endsOn: DateTime::create('2026-10-01 00:00:00'),
    );
    $repository->create($older);
    $repository->create($newer);

    $client->request('GET', '/kal', server: apiJsonHeaders());

    $payload = json_decode((string) $client->getResponse()->getContent(), true);

    expect(array_column($payload['data'], 'id'))
        ->toBe([$newer->id->value(), $older->id->value()]);
});

it('answers 401 without an authorization header', function (): void {
    $client = static::createClient();

    $client->request('GET', '/kal', server: ['CONTENT_TYPE' => 'application/json']);

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);
});
