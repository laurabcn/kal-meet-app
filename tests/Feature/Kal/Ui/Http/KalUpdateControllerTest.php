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

it('updates a kal and answers 204 with an empty body', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);

    $kal = KalMother::create(
        organizerId: UlidValue::create(StubTokenHandler::USER_ID),
        name: NonEmptyStringValue::create('Before'),
    );
    $repository->create($kal);

    $client->request(
        'PATCH',
        '/kal/'.$kal->id->value(),
        server: apiJsonHeaders(),
        content: (string) json_encode(['name' => 'After']),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_NO_CONTENT)
        ->and($client->getResponse()->getContent())->toBe('');

    expect($repository->all()[0]->name->value())->toBe('After');
});

it('hands a partial patch through the command bus without clearing omitted fields', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);

    $kal = KalMother::create(
        organizerId: UlidValue::create(StubTokenHandler::USER_ID),
        description: NonEmptyStringValue::create('Keep description'),
        coverPath: 'keep.webp',
    );
    $repository->create($kal);

    $client->request(
        'PATCH',
        '/kal/'.$kal->id->value(),
        server: apiJsonHeaders(),
        content: (string) json_encode(['name' => 'Only name']),
    );

    $updated = $repository->all()[0];
    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_NO_CONTENT)
        ->and($updated->name->value())->toBe('Only name')
        ->and($updated->description?->value())->toBe('Keep description')
        ->and($updated->coverPath)->toBe('keep.webp');
});

it('clears nullable fields when the body sends null', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);

    $kal = KalMother::create(
        organizerId: UlidValue::create(StubTokenHandler::USER_ID),
        description: NonEmptyStringValue::create('Gone'),
        endsOn: DateTime::create('2026-09-01 00:00:00'),
        coverPath: 'gone.webp',
    );
    $repository->create($kal);

    $client->request(
        'PATCH',
        '/kal/'.$kal->id->value(),
        server: apiJsonHeaders(),
        content: (string) json_encode([
            'description' => null,
            'endsOn' => null,
            'coverPath' => null,
        ]),
    );

    $updated = $repository->all()[0];
    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_NO_CONTENT)
        ->and($updated->description)->toBeNull()
        ->and($updated->endsOn)->toBeNull()
        ->and($updated->coverPath)->toBeNull();
});

it('answers 204 for an empty patch without changing the kal', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);

    $kal = KalMother::create(
        organizerId: UlidValue::create(StubTokenHandler::USER_ID),
        name: NonEmptyStringValue::create('Unchanged'),
    );
    $repository->create($kal);

    $client->request(
        'PATCH',
        '/kal/'.$kal->id->value(),
        server: apiJsonHeaders(),
        content: '{}',
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_NO_CONTENT)
        ->and($repository->all()[0]->name->value())->toBe('Unchanged');
});

it('answers 404 when the authenticated user is not the organizer', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);

    $kal = KalMother::create();
    $repository->create($kal);

    $client->request(
        'PATCH',
        '/kal/'.$kal->id->value(),
        server: apiJsonHeaders(),
        content: (string) json_encode(['name' => 'Nope']),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_NOT_FOUND)
        ->and($client->getResponse()->getContent())->toBe('{"error":"Kal not found.","code":"kal_not_found"}');
});

it('answers 404 when the kal does not exist', function (): void {
    $client = static::createClient();

    $client->request(
        'PATCH',
        '/kal/01J5M6XQBR4GTYHN8KZXP0F1W9',
        server: apiJsonHeaders(),
        content: (string) json_encode(['name' => 'Nope']),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_NOT_FOUND)
        ->and($client->getResponse()->getContent())->toBe('{"error":"Kal not found.","code":"kal_not_found"}');
});

it('answers 404 when the kal is soft-deleted', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);

    $kal = KalMother::create(organizerId: UlidValue::create(StubTokenHandler::USER_ID));
    $repository->create($kal);
    $repository->softDelete($kal->id->value());

    $client->request(
        'PATCH',
        '/kal/'.$kal->id->value(),
        server: apiJsonHeaders(),
        content: (string) json_encode(['name' => 'Nope']),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_NOT_FOUND)
        ->and($client->getResponse()->getContent())->toBe('{"error":"Kal not found.","code":"kal_not_found"}');
});

it('answers 400 when the id is not a valid ulid', function (): void {
    $client = static::createClient();

    $client->request(
        'PATCH',
        '/kal/not-a-ulid',
        server: apiJsonHeaders(),
        content: (string) json_encode(['name' => 'Nope']),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($client->getResponse()->getContent())->toBe('{"error":"The request payload is invalid.","code":"invalid_payload"}');
});

it('answers 400 when the body is not json', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);

    $kal = KalMother::create(organizerId: UlidValue::create(StubTokenHandler::USER_ID));
    $repository->create($kal);

    $client->request(
        'PATCH',
        '/kal/'.$kal->id->value(),
        server: apiJsonHeaders(),
        content: 'not json at all',
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($client->getResponse()->getContent())->toBe('{"error":"The request body is not valid JSON.","code":"invalid_json"}');
});

it('answers 400 when organizerId is sent in the body', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);

    $kal = KalMother::create(organizerId: UlidValue::create(StubTokenHandler::USER_ID));
    $repository->create($kal);

    $client->request(
        'PATCH',
        '/kal/'.$kal->id->value(),
        server: apiJsonHeaders(),
        content: (string) json_encode(['name' => 'X', 'organizerId' => StubTokenHandler::USER_ID]),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($client->getResponse()->getContent())->toBe('{"error":"The request payload is invalid.","code":"invalid_payload"}');
});

it('answers 400 when inviteToken is sent in the body', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);

    $kal = KalMother::create(organizerId: UlidValue::create(StubTokenHandler::USER_ID));
    $repository->create($kal);

    $client->request(
        'PATCH',
        '/kal/'.$kal->id->value(),
        server: apiJsonHeaders(),
        content: (string) json_encode(['inviteToken' => 'should-not-rotate']),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($client->getResponse()->getContent())->toBe('{"error":"The request payload is invalid.","code":"invalid_payload"}');
});

it('answers 400 when the date range is invalid', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);

    $kal = KalMother::create(
        organizerId: UlidValue::create(StubTokenHandler::USER_ID),
        startsOn: DateTime::create('2026-08-01 00:00:00'),
    );
    $repository->create($kal);

    $client->request(
        'PATCH',
        '/kal/'.$kal->id->value(),
        server: apiJsonHeaders(),
        content: (string) json_encode(['endsOn' => '2026-07-01 00:00:00']),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($client->getResponse()->getContent())->toBe('{"error":"The kal end date must be after the start date.","code":"kal_invalid_date_range"}');
});

it('answers 401 without an authorization header', function (): void {
    $client = static::createClient();

    $client->request(
        'PATCH',
        '/kal/01J5M6XQBR4GTYHN8KZXP0F1W9',
        server: ['CONTENT_TYPE' => 'application/json'],
        content: (string) json_encode(['name' => 'Nope']),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);
});
