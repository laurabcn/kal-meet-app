<?php

declare(strict_types=1);

use App\Kal\Domain\Repository\KalRepositoryInterface;
use App\Shared\Domain\ValueObject\UlidValue;
use Symfony\Component\HttpFoundation\Response;
use Tests\Unit\Kal\Domain\Mother\KalMother;
use Tests\Unit\Kal\Infrastructure\Persistence\InMemoryKalRepository;
use Tests\Unit\Shared\Infrastructure\Symfony\Security\StubTokenHandler;

it('answers 200 with the organizer view including the invite token', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);

    $kal = KalMother::create(organizerId: UlidValue::create(StubTokenHandler::USER_ID));
    $repository->create($kal);

    $client->request('GET', '/kal/'.$kal->id->value(), server: apiAuthHeaders());

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_OK);

    /** @var array{data: array<string, mixed>} $body */
    $body = json_decode((string) $client->getResponse()->getContent(), true);
    expect($body['data']['id'])->toBe($kal->id->value())
        ->and($body['data']['name'])->toBe($kal->name->value())
        ->and($body['data']['inviteToken'])->toBe($kal->inviteToken->value())
        ->and($body['data']['inviteToken'])->not->toBeEmpty()
        ->and($body['data'])->not->toHaveKey('organizerId')
        ->and($body['data']['meetings'])->toBe([])
        ->and($body['data']['clues'])->toBe([])
        ->and($body['data']['files'])->toBe([]);
});

it('answers 404 when the authenticated user is not the organizer', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $repository */
    $repository = static::getContainer()->get(KalRepositoryInterface::class);

    // Organitzat per algú altre, no per StubTokenHandler::USER_ID.
    $kal = KalMother::create();
    $repository->create($kal);

    $client->request('GET', '/kal/'.$kal->id->value(), server: apiAuthHeaders());

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_NOT_FOUND)
        ->and($client->getResponse()->getContent())->toBe('{"error":"Kal not found.","code":"kal_not_found"}');
});

it('answers 404 when the kal does not exist', function (): void {
    $client = static::createClient();

    $client->request('GET', '/kal/01J5M6XQBR4GTYHN8KZXP0F1W9', server: apiAuthHeaders());

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

    $client->request('GET', '/kal/'.$kal->id->value(), server: apiAuthHeaders());

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_NOT_FOUND)
        ->and($client->getResponse()->getContent())->toBe('{"error":"Kal not found.","code":"kal_not_found"}');
});

it('answers 400 when the id is not a valid ulid', function (): void {
    $client = static::createClient();

    $client->request('GET', '/kal/not-a-ulid', server: apiAuthHeaders());

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($client->getResponse()->getContent())->toBe('{"error":"The request payload is invalid.","code":"invalid_payload"}');
});

it('answers 401 without an authorization header', function (): void {
    $client = static::createClient();

    $client->request('GET', '/kal/01J5M6XQBR4GTYHN8KZXP0F1W9');

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);
});
