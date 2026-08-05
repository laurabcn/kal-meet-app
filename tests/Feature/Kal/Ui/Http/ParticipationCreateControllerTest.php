<?php

declare(strict_types=1);

use App\Kal\Domain\Participation;
use App\Kal\Domain\Repository\KalRepositoryInterface;
use App\Kal\Domain\Repository\ParticipationRepositoryInterface;
use App\Shared\Domain\ValueObject\UlidValue;
use Symfony\Component\HttpFoundation\Response;
use Tests\Unit\Kal\Domain\Mother\KalMother;
use Tests\Unit\Kal\Infrastructure\Persistence\InMemoryKalRepository;
use Tests\Unit\Kal\Infrastructure\Persistence\InMemoryParticipationRepository;
use Tests\Unit\Shared\Infrastructure\Symfony\Security\StubTokenHandler;

it('joins a kal and answers 201 carrying no data', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $kalRepository */
    $kalRepository = static::getContainer()->get(KalRepositoryInterface::class);
    /** @var InMemoryParticipationRepository $participationRepository */
    $participationRepository = static::getContainer()->get(ParticipationRepositoryInterface::class);

    $kal = KalMother::create();
    $kalRepository->create($kal);

    $client->request(
        'POST',
        '/kal/participation',
        server: apiJsonHeaders(),
        content: (string) json_encode([
            'kalId' => $kal->id->value(),
            'inviteToken' => $kal->inviteToken->value(),
        ]),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_CREATED)
        ->and($client->getResponse()->getContent())->toBe('{}')
        ->and($participationRepository->all())->toHaveCount(1)
        ->and($participationRepository->all()[0]->userId->value())->toBe(StubTokenHandler::USER_ID)
        ->and($participationRepository->all()[0]->kalId->value())->toBe($kal->id->value());
});

it('answers 409 when the caller is the organizer', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $kalRepository */
    $kalRepository = static::getContainer()->get(KalRepositoryInterface::class);
    /** @var InMemoryParticipationRepository $participationRepository */
    $participationRepository = static::getContainer()->get(ParticipationRepositoryInterface::class);

    $kal = KalMother::create(organizerId: UlidValue::create(StubTokenHandler::USER_ID));
    $kalRepository->create($kal);

    $client->request(
        'POST',
        '/kal/participation',
        server: apiJsonHeaders(),
        content: (string) json_encode([
            'kalId' => $kal->id->value(),
            'inviteToken' => $kal->inviteToken->value(),
        ]),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_CONFLICT)
        ->and($client->getResponse()->getContent())->toBe(
            '{"error":"User is already a member of this kal.","code":"kal_already_member"}',
        )
        ->and($participationRepository->all())->toBeEmpty();
});

it('answers 409 when the caller is already a member', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $kalRepository */
    $kalRepository = static::getContainer()->get(KalRepositoryInterface::class);
    /** @var InMemoryParticipationRepository $participationRepository */
    $participationRepository = static::getContainer()->get(ParticipationRepositoryInterface::class);

    $kal = KalMother::create();
    $kalRepository->create($kal);
    $participationRepository->create(Participation::create(
        UlidValue::generate(),
        $kal->id,
        UlidValue::create(StubTokenHandler::USER_ID),
    ));

    $client->request(
        'POST',
        '/kal/participation',
        server: apiJsonHeaders(),
        content: (string) json_encode([
            'kalId' => $kal->id->value(),
            'inviteToken' => $kal->inviteToken->value(),
        ]),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_CONFLICT)
        ->and($client->getResponse()->getContent())->toBe(
            '{"error":"User is already a member of this kal.","code":"kal_already_member"}',
        )
        ->and($participationRepository->all())->toHaveCount(1);
});

it('answers 404 when the kal does not exist', function (): void {
    $client = static::createClient();

    $client->request(
        'POST',
        '/kal/participation',
        server: apiJsonHeaders(),
        content: (string) json_encode([
            'kalId' => '01J5M6XQBR4GTYHN8KZXP0F1W9',
            'inviteToken' => 'deadbeefdeadbeefdeadbeefdeadbeef',
        ]),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_NOT_FOUND)
        ->and($client->getResponse()->getContent())->toBe('{"error":"Kal not found.","code":"kal_not_found"}');
});

it('answers 404 when the kal is soft-deleted', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $kalRepository */
    $kalRepository = static::getContainer()->get(KalRepositoryInterface::class);

    $kal = KalMother::create();
    $kalRepository->create($kal);
    $kalRepository->softDelete($kal->id->value());

    $client->request(
        'POST',
        '/kal/participation',
        server: apiJsonHeaders(),
        content: (string) json_encode([
            'kalId' => $kal->id->value(),
            'inviteToken' => $kal->inviteToken->value(),
        ]),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_NOT_FOUND)
        ->and($client->getResponse()->getContent())->toBe('{"error":"Kal not found.","code":"kal_not_found"}');
});

it('answers 404 when the invite token does not match', function (): void {
    $client = static::createClient();
    /** @var InMemoryKalRepository $kalRepository */
    $kalRepository = static::getContainer()->get(KalRepositoryInterface::class);

    $kal = KalMother::create();
    $kalRepository->create($kal);

    $client->request(
        'POST',
        '/kal/participation',
        server: apiJsonHeaders(),
        content: (string) json_encode([
            'kalId' => $kal->id->value(),
            'inviteToken' => 'deadbeefdeadbeefdeadbeefdeadbeef',
        ]),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_NOT_FOUND)
        ->and($client->getResponse()->getContent())->toBe('{"error":"Kal not found.","code":"kal_not_found"}');
});

it('answers 400 when the body is invalid', function (): void {
    $client = static::createClient();

    $client->request('POST', '/kal/participation', server: apiJsonHeaders(), content: '{}');

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($client->getResponse()->getContent())->toBe(
            '{"error":"The request payload is invalid.","code":"invalid_payload"}',
        );
});

it('answers 400 when the body is not json', function (): void {
    $client = static::createClient();

    $client->request('POST', '/kal/participation', server: apiJsonHeaders(), content: 'not json at all');

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($client->getResponse()->getContent())->toBe(
            '{"error":"The request body is not valid JSON.","code":"invalid_json"}',
        );
});

it('answers 400 when kalId is not a ulid', function (): void {
    $client = static::createClient();

    $client->request(
        'POST',
        '/kal/participation',
        server: apiJsonHeaders(),
        content: (string) json_encode([
            'kalId' => 'not-a-ulid',
            'inviteToken' => 'deadbeefdeadbeefdeadbeefdeadbeef',
        ]),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($client->getResponse()->getContent())->toBe(
            '{"error":"The request payload is invalid.","code":"invalid_payload"}',
        );
});

it('answers 401 without an authorization header', function (): void {
    $client = static::createClient();

    $client->request(
        'POST',
        '/kal/participation',
        server: ['CONTENT_TYPE' => 'application/json'],
        content: (string) json_encode([
            'kalId' => '01J5M6XQBR4GTYHN8KZXP0F1W9',
            'inviteToken' => 'deadbeefdeadbeefdeadbeefdeadbeef',
        ]),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED)
        ->and($client->getResponse()->getContent())->toBe(
            '{"error":"Authentication token is missing.","code":"auth_token_missing"}',
        );
});
