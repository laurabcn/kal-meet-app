<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Symfony\Security\Exception\JwksFetchFailedException;
use App\Shared\Infrastructure\Symfony\Security\HttpJwksFetcher;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

// Deu línies, però sostenen tota l'autenticació: si el fetcher deixés escapar
// l'excepció crua de HttpClient en comptes de `JwksFetchFailedException`, una
// caiguda del JWKS de Supabase respondria 500 `internal_error` en comptes del
// 503 `auth_keys_unavailable` documentat. El test de firewall que cobreix aquell
// 503 usa un stub, o sigui que seguiria en verd sense assabentar-se de res.

const JWKS_URL = 'https://project.supabase.co/auth/v1/.well-known/jwks.json';

it('returns the decoded key set', function (): void {
    $client = new MockHttpClient(new MockResponse(
        (string) json_encode(['keys' => [['kid' => 'abc', 'kty' => 'RSA']]]),
        ['response_headers' => ['content-type' => 'application/json']],
    ));

    $keys = (new HttpJwksFetcher($client, JWKS_URL))->fetch();

    expect($keys)->toBe(['keys' => [['kid' => 'abc', 'kty' => 'RSA']]]);
});

it('requests the configured url with the configured timeout', function (): void {
    $seen = null;
    $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$seen): MockResponse {
        $seen = ['method' => $method, 'url' => $url, 'timeout' => $options['timeout'] ?? null];

        return new MockResponse('{"keys":[]}', ['response_headers' => ['content-type' => 'application/json']]);
    });

    (new HttpJwksFetcher($client, JWKS_URL, 3))->fetch();

    expect($seen['method'])->toBe('GET')
        ->and($seen['url'])->toBe(JWKS_URL)
        ->and($seen['timeout'])->toBe(3.0);
});

it('translates a server error into jwks_fetch_failed', function (): void {
    $client = new MockHttpClient(new MockResponse('upstream is down', ['http_code' => 500]));

    expect(fn () => (new HttpJwksFetcher($client, JWKS_URL))->fetch())
        ->toThrow(JwksFetchFailedException::class);
});

it('translates a transport failure into jwks_fetch_failed', function (): void {
    $client = new MockHttpClient(static function (): never {
        throw new Symfony\Component\HttpClient\Exception\TransportException('connection refused');
    });

    expect(fn () => (new HttpJwksFetcher($client, JWKS_URL))->fetch())
        ->toThrow(JwksFetchFailedException::class);
});

// Un 200 amb un cos que no és JSON és el cas que més fàcilment s'escapa: passa
// el codi d'estat i peta al descodificar.
it('translates a non-json body into jwks_fetch_failed', function (): void {
    $client = new MockHttpClient(new MockResponse(
        '<html>maintenance</html>',
        ['response_headers' => ['content-type' => 'text/html']],
    ));

    expect(fn () => (new HttpJwksFetcher($client, JWKS_URL))->fetch())
        ->toThrow(JwksFetchFailedException::class);
});

it('keeps the original failure as the exception cause', function (): void {
    $client = new MockHttpClient(new MockResponse('nope', ['http_code' => 503]));

    try {
        (new HttpJwksFetcher($client, JWKS_URL))->fetch();
        $this->fail('Expected JwksFetchFailedException');
    } catch (JwksFetchFailedException $exception) {
        expect($exception->getPrevious())->not->toBeNull();
    }
});
