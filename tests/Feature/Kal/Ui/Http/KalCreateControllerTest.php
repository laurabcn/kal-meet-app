<?php

declare(strict_types=1);

use App\Kal\Domain\Repository\KalRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\Unit\Kal\Infrastructure\Persistence\InMemoryKalRepository;
use Tests\Unit\Shared\Infrastructure\Symfony\Security\StubTokenHandler;

/**
 * @param array<string, mixed> $overrides
 *
 * @return array<string, mixed>
 */
function kalPayload(array $overrides = []): array
{
    return [
        // Cap `organizerId`: surt del token (spec §3.4) i enviar-lo és un 400.
        // `id` el mint el client (spec api-response).
        'id' => '01J5M6XQBR4GTYHN8KZXP0F1W3',
        'name' => 'KAL de tardor',
        'startsOn' => '2026-09-01 00:00:00',
        'endsOn' => '2026-10-01 00:00:00',
        'locales' => ['ca', 'es'],
        ...$overrides,
    ];
}

it('creates a kal and answers 201 carrying no data', function (): void {
    $client = static::createClient();

    $client->request('POST', '/kal', server: apiJsonHeaders(), content: (string) json_encode(kalPayload()));

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_CREATED)
        ->and($client->getResponse()->getContent())->toBe('{}');
});

it('hands the payload to the domain through the command bus', function (): void {
    $client = static::createClient();

    $client->request('POST', '/kal', server: apiJsonHeaders(), content: (string) json_encode(kalPayload(['name' => "Xal d'estiu"])));

    $repository = static::getContainer()->get(KalRepositoryInterface::class);
    expect($repository)->toBeInstanceOf(InMemoryKalRepository::class);

    $kals = $repository->all();
    expect($kals)->toHaveCount(1)
        ->and($kals[0]->name->value())->toBe("Xal d'estiu")
        ->and($kals[0]->id->value())->toBe('01J5M6XQBR4GTYHN8KZXP0F1W3')
        ->and($kals[0]->organizerId->value())->toBe(StubTokenHandler::USER_ID)
        ->and($kals[0]->startsOn->value())->toBe('2026-09-01 00:00:00')
        ->and($kals[0]->locales->all())->toHaveCount(2)
        ->and($kals[0]->inviteToken->value())->not->toBeEmpty();
});

it('answers 400 when the body is not json', function (): void {
    $client = static::createClient();

    $client->request('POST', '/kal', server: apiJsonHeaders(), content: 'not json at all');

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($client->getResponse()->getContent())->toBe('{"error":"The request body is not valid JSON.","code":"invalid_json"}');
});

it('answers 400 when the body is an empty json object', function (): void {
    $client = static::createClient();

    $client->request('POST', '/kal', server: apiJsonHeaders(), content: '{}');

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($client->getResponse()->getContent())->toBe('{"error":"The request payload is invalid.","code":"invalid_payload"}');
});

it('answers 400 when locales is not a list', function (): void {
    $client = static::createClient();

    $client->request('POST', '/kal', server: apiJsonHeaders(), content: (string) json_encode(kalPayload(['locales' => 'ca'])));

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($client->getResponse()->getContent())->toBe('{"error":"The request payload is invalid.","code":"invalid_payload"}');
});

it('rejects a body carrying organizerId, even if it matches the token', function (): void {
    $client = static::createClient();

    // Ni tan sols el "seu" val: el camp no és del contracte. Si s'acceptés
    // quan coincideix, el dia que algú deixés de comparar-lo tornaria a
    // obrir-se la suplantació sencera.
    $client->request('POST', '/kal', server: apiJsonHeaders(), content: (string) json_encode(kalPayload(['organizerId' => StubTokenHandler::USER_ID])));

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($client->getResponse()->getContent())->toBe('{"error":"The request payload is invalid.","code":"invalid_payload"}');
});

it('creates no kal attributed to someone else through the body', function (): void {
    $client = static::createClient();

    // Un ULID que no és el del token: el cas de suplantació del spec §1.
    $client->request('POST', '/kal', server: apiJsonHeaders(), content: (string) json_encode(kalPayload(['organizerId' => '01J7A2C4E6G8K0M2P4R6T8V0X1'])));

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST);

    $repository = static::getContainer()->get(KalRepositoryInterface::class);
    expect($repository)->toBeInstanceOf(InMemoryKalRepository::class)
        ->and($repository->all())->toBeEmpty();
});

it('persists nothing when the payload is rejected', function (): void {
    $client = static::createClient();

    $client->request('POST', '/kal', server: apiJsonHeaders(), content: (string) json_encode(kalPayload(['name' => ''])));

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST);

    $repository = static::getContainer()->get(KalRepositoryInterface::class);
    expect($repository)->toBeInstanceOf(InMemoryKalRepository::class)
        ->and($repository->all())->toBeEmpty();
});

it('answers 400 when id is not a ulid', function (): void {
    $client = static::createClient();

    $client->request('POST', '/kal', server: apiJsonHeaders(), content: (string) json_encode(kalPayload(['id' => 'not-a-ulid'])));

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($client->getResponse()->getContent())->toBe('{"error":"The request payload is invalid.","code":"invalid_payload"}');
});

it('answers 400 when a domain invariant is violated', function (): void {
    $client = static::createClient();

    $client->request(
        'POST',
        '/kal',
        server: apiJsonHeaders(),
        content: (string) json_encode(kalPayload([
            'startsOn' => '2026-10-01 00:00:00',
            'endsOn' => '2026-09-01 00:00:00',
        ])),
    );

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($client->getResponse()->getContent())->toBe('{"error":"The kal end date must be after the start date.","code":"kal_invalid_date_range"}');
});

it('answers 409 when the kal id already exists', function (): void {
    $client = static::createClient();
    $client->disableReboot();
    $payload = (string) json_encode(kalPayload());

    $client->request('POST', '/kal', server: apiJsonHeaders(), content: $payload);
    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_CREATED);

    $client->request('POST', '/kal', server: apiJsonHeaders(), content: $payload);

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_CONFLICT)
        ->and($client->getResponse()->getContent())->toBe('{"error":"A kal with this id already exists.","code":"kal_already_exists"}');
});

// GET /kal ja no serveix: des del llistat de l'organitzadora és una ruta de
// veritat. PUT segueix sense estar assignada a /kal.
it('answers 405 for a method neither POST nor GET', function (): void {
    $client = static::createClient();

    $client->request('PUT', '/kal');

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_METHOD_NOT_ALLOWED);
});
