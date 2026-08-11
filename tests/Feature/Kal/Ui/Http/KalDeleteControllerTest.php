<?php

declare(strict_types=1);

use App\Kal\Domain\Repository\KalRepositoryInterface;
use App\Shared\Domain\ValueObject\UlidValue;
use Symfony\Component\HttpFoundation\Response;
use Tests\Unit\Kal\Domain\Mother\KalMother;
use Tests\Unit\Kal\Infrastructure\Persistence\InMemoryKalRepository;
use Tests\Unit\Shared\Infrastructure\Symfony\Security\StubTokenHandler;

it('deletes a kal and answers 204 with an empty body', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);

    $kal = KalMother::create(organizerId: UlidValue::create(StubTokenHandler::USER_ID));
    $repository->create($kal);

    $client->request('DELETE', '/kal/'.$kal->id->value(), server: apiJsonHeaders());

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_NO_CONTENT)
        ->and($client->getResponse()->getContent())->toBe('')
        ->and($repository->isDeleted($kal->id->value()))->toBeTrue();
});

it('stops answering the get of a kal it just deleted', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);

    $kal = KalMother::create(organizerId: UlidValue::create(StubTokenHandler::USER_ID));
    $repository->create($kal);

    $client->request('DELETE', '/kal/'.$kal->id->value(), server: apiJsonHeaders());
    $client->request('GET', '/kal/'.$kal->id->value(), server: apiJsonHeaders());

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_NOT_FOUND)
        ->and($client->getResponse()->getContent())->toBe('{"error":"Kal not found.","code":"kal_not_found"}');
});

it('answers 404 when deleting twice', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);

    $kal = KalMother::create(organizerId: UlidValue::create(StubTokenHandler::USER_ID));
    $repository->create($kal);

    $client->request('DELETE', '/kal/'.$kal->id->value(), server: apiJsonHeaders());
    $client->request('DELETE', '/kal/'.$kal->id->value(), server: apiJsonHeaders());

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_NOT_FOUND)
        ->and($client->getResponse()->getContent())->toBe('{"error":"Kal not found.","code":"kal_not_found"}');
});

it('answers 404 when the authenticated user is not the organizer', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);

    $kal = KalMother::create();
    $repository->create($kal);

    $client->request('DELETE', '/kal/'.$kal->id->value(), server: apiJsonHeaders());

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_NOT_FOUND)
        ->and($client->getResponse()->getContent())->toBe('{"error":"Kal not found.","code":"kal_not_found"}')
        ->and($repository->isDeleted($kal->id->value()))->toBeFalse();
});

it('answers 404 when the kal does not exist', function (): void {
    $client = static::createClient();

    $client->request('DELETE', '/kal/01J5M6XQBR4GTYHN8KZXP0F1W9', server: apiJsonHeaders());

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_NOT_FOUND)
        ->and($client->getResponse()->getContent())->toBe('{"error":"Kal not found.","code":"kal_not_found"}');
});

it('answers 400 when the id is not a valid ulid', function (): void {
    $client = static::createClient();

    $client->request('DELETE', '/kal/not-a-ulid', server: apiJsonHeaders());

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($client->getResponse()->getContent())->toBe('{"error":"The request payload is invalid.","code":"invalid_payload"}');
});

it('answers 401 without an authorization header', function (): void {
    $client = static::createClient();

    $client->request('DELETE', '/kal/01J5M6XQBR4GTYHN8KZXP0F1W9', server: ['CONTENT_TYPE' => 'application/json']);

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);
});
