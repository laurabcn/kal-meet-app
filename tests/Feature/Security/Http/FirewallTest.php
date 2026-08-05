<?php

declare(strict_types=1);

namespace Tests\Feature\Security\Http;

use App\Kal\Domain\Repository\KalRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\Unit\Kal\Infrastructure\Persistence\InMemoryKalRepository;
use Tests\Unit\Shared\Infrastructure\Symfony\Security\StubTokenHandler;

// A l'entorn de test el token handler és un doble (config/services_test.yaml):
// el que es prova aquí és el firewall i el mapatge d'errors de §3.5 tal com
// surten pel kernel HTTP de veritat. La verificació criptogràfica que decideix
// QUINA fallada toca a cada token viu a
// tests/Unit/Shared/Infrastructure/Symfony/Security/SupabaseTokenHandlerTest.php.

it('answers auth_token_missing without an Authorization header', function (): void {
    $client = static::createClient();

    $client->request('POST', '/kal', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED)
        ->and($client->getResponse()->getContent())->toBe('{"error":"Authentication token is missing.","code":"auth_token_missing"}');
});

it('answers auth_token_missing when the scheme is not Bearer', function (): void {
    $client = static::createClient();

    $client->request('POST', '/kal', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_AUTHORIZATION' => 'Basic dXNlcjpwYXNz',
    ], content: '{}');

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED)
        ->and($client->getResponse()->getContent())->toBe('{"error":"Authentication token is missing.","code":"auth_token_missing"}');
});

it('rejects before validating the payload', function (): void {
    $client = static::createClient();

    // El cos és invàlid i el token hi falta: ha de guanyar l'auth (spec §5, cas 3).
    $client->request('POST', '/kal', server: ['CONTENT_TYPE' => 'application/json'], content: 'not json');

    expect($client->getResponse()->getContent())->toBe('{"error":"Authentication token is missing.","code":"auth_token_missing"}');
});

it('creates nothing at all when the request is unauthenticated', function (): void {
    $client = static::createClient();

    // El 401 sol no diu res del que ha passat a sota: el payload és
    // perfectament vàlid i el que es comprova és que no arribi al domini.
    $client->request('POST', '/kal', server: ['CONTENT_TYPE' => 'application/json'], content: (string) json_encode([
        'name' => 'KAL de tardor',
        'startsOn' => '2026-09-01 00:00:00',
        'locales' => ['ca'],
    ]));

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);

    $repository = static::getContainer()->get(KalRepositoryInterface::class);
    expect($repository)->toBeInstanceOf(InMemoryKalRepository::class)
        ->and($repository->all())->toBeEmpty();
});

it('answers auth_token_invalid when the handler rejects the token', function (): void {
    $client = static::createClient();

    $client->request('POST', '/kal', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_AUTHORIZATION' => 'Bearer not-a-jwt',
    ], content: '{}');

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED)
        ->and($client->getResponse()->getContent())->toBe('{"error":"Authentication token is invalid.","code":"auth_token_invalid"}');
});

it('lets an authenticated request through to the controller', function (): void {
    $client = static::createClient();

    // El 400 és la prova que ha passat el firewall: el cos buit el rebutja el
    // controller, no l'autenticació.
    $client->request('POST', '/kal', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_AUTHORIZATION' => 'Bearer '.StubTokenHandler::TOKEN,
    ], content: '{}');

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($client->getResponse()->getContent())->toBe('{"error":"The request payload is invalid.","code":"invalid_payload"}');
});

it('answers auth_token_expired, distinguishable from an invalid token', function (): void {
    $client = static::createClient();

    $client->request('POST', '/kal', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_AUTHORIZATION' => 'Bearer '.StubTokenHandler::EXPIRED_TOKEN,
    ], content: '{}');

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED)
        ->and($client->getResponse()->getContent())->toBe('{"error":"Authentication token has expired.","code":"auth_token_expired"}');
});

it('answers auth_profile_not_found when the token is good but the profile is gone', function (): void {
    $client = static::createClient();

    $client->request('POST', '/kal', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_AUTHORIZATION' => 'Bearer '.StubTokenHandler::NO_PROFILE_TOKEN,
    ], content: '{}');

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED)
        ->and($client->getResponse()->getContent())->toBe('{"error":"User profile was not found.","code":"auth_profile_not_found"}');
});

it('answers 503 auth_keys_unavailable when there are no verification keys', function (): void {
    $client = static::createClient();

    // Escenari 11: en fred i amb Supabase caigut no hi ha manera de verificar
    // res. El client no hi té culpa, i per això és l'únic cas que no és 401.
    $client->request('POST', '/kal', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_AUTHORIZATION' => 'Bearer '.StubTokenHandler::KEYS_DOWN_TOKEN,
    ], content: '{}');

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_SERVICE_UNAVAILABLE)
        ->and($client->getResponse()->getContent())->toBe('{"error":"Authentication keys are temporarily unavailable.","code":"auth_keys_unavailable"}');
});

it('keeps the health check public', function (): void {
    $client = static::createClient();

    $client->request('GET', '/');

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_OK);
});

// Escenari 12: la propietat que justifica el firewall. La ruta /test-probe
// (tests/Feature/Security/Support/ProbeController.php) es va afegir després de
// l'autenticació i no apareix enlloc de config/packages/security.yaml.

it('protects a route added afterwards without touching security.yaml', function (): void {
    $client = static::createClient();

    $client->request('GET', '/test-probe');

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED)
        ->and($client->getResponse()->getContent())->toBe('{"error":"Authentication token is missing.","code":"auth_token_missing"}');
});

it('lets that same new route through once authenticated', function (): void {
    // El contrapès del test anterior: si la sonda respongués 401 sempre (una
    // ruta mal registrada, p.ex.) el de dalt passaria per la raó equivocada.
    $client = static::createClient();

    $client->request('GET', '/test-probe', server: ['HTTP_AUTHORIZATION' => 'Bearer '.StubTokenHandler::TOKEN]);

    expect($client->getResponse()->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($client->getResponse()->getContent())->toBe('{"reached":true}');
});
