<?php

declare(strict_types=1);

use App\Kal\Domain\KalRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\Kal\Infrastructure\Persistence\InMemoryKalRepository;

/**
 * @param array<string, mixed> $overrides
 *
 * @return array<string, mixed>
 */
function kalPayload(array $overrides = []): array
{
    return [
        'organizerId' => '01J5M6XQBR4GTYHN8KZXP0F1W2',
        'name' => 'KAL de tardor',
        'startsOn' => '2026-09-01 00:00:00',
        'endsOn' => '2026-10-01 00:00:00',
        'locales' => ['ca', 'es'],
        ...$overrides,
    ];
}

it('creates a kal and answers 201 carrying no data', function (): void {
    $client = static::createClient();

    $client->request('POST', '/kal', server: ['CONTENT_TYPE' => 'application/json'], content: (string) json_encode(kalPayload()));

    // El `{}` és `JsonResponse(null)`, no una decisió de contracte: què retorna
    // una escriptura (l'id del KAL creat, sobretot) es decideix a la branca de
    // docs/specs/kal-http-response-and-errors.md. Aquest test fixa el que fa
    // avui perquè aquell canvi es vegi quan arribi.
    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_CREATED)
        ->and($client->getResponse()->getContent())->toBe('{}');
});

it('hands the payload to the domain through the command bus', function (): void {
    $client = static::createClient();

    $client->request('POST', '/kal', server: ['CONTENT_TYPE' => 'application/json'], content: (string) json_encode(kalPayload(['name' => "Xal d'estiu"])));

    $repository = static::getContainer()->get(KalRepositoryInterface::class);
    expect($repository)->toBeInstanceOf(InMemoryKalRepository::class);

    $kals = $repository->all();
    expect($kals)->toHaveCount(1)
        ->and($kals[0]->name->value())->toBe("Xal d'estiu")
        ->and($kals[0]->organizerId->value())->toBe('01J5M6XQBR4GTYHN8KZXP0F1W2')
        ->and($kals[0]->startsOn->value())->toBe('2026-09-01 00:00:00')
        ->and($kals[0]->locales->all())->toHaveCount(2)
        ->and($kals[0]->inviteToken->value())->not->toBeEmpty();
});

it('answers 400 when the body is not json', function (): void {
    $client = static::createClient();

    $client->request('POST', '/kal', server: ['CONTENT_TYPE' => 'application/json'], content: 'not json at all');

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($client->getResponse()->getContent())->toBe('{"error":"kal_invalid_json"}');
});

it('answers 400 when the body is an empty json object', function (): void {
    $client = static::createClient();

    $client->request('POST', '/kal', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($client->getResponse()->getContent())->toBe('{"error":"kal_invalid_payload"}');
});

it('answers 400 when locales is not a list', function (): void {
    $client = static::createClient();

    $client->request('POST', '/kal', server: ['CONTENT_TYPE' => 'application/json'], content: (string) json_encode(kalPayload(['locales' => 'ca'])));

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($client->getResponse()->getContent())->toBe('{"error":"kal_invalid_payload"}');
});

it('persists nothing when the payload is rejected', function (): void {
    $client = static::createClient();

    $client->request('POST', '/kal', server: ['CONTENT_TYPE' => 'application/json'], content: (string) json_encode(kalPayload(['name' => ''])));

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST);

    $repository = static::getContainer()->get(KalRepositoryInterface::class);
    expect($repository)->toBeInstanceOf(InMemoryKalRepository::class)
        ->and($repository->all())->toBeEmpty();
});

it('answers 405 for a method other than POST', function (): void {
    $client = static::createClient();

    $client->request('GET', '/kal');

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_METHOD_NOT_ALLOWED);
});
