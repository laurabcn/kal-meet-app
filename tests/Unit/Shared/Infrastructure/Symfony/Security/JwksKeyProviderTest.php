<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Symfony\Security;

use App\Shared\Infrastructure\Symfony\Security\Exception\JwksFetchFailedException;
use App\Shared\Infrastructure\Symfony\Security\Exception\VerificationKeysUnavailableException;
use App\Shared\Infrastructure\Symfony\Security\JwksFetcherInterface;
use App\Shared\Infrastructure\Symfony\Security\JwksKeyProvider;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

// El doble del token handler (config/services_test.yaml) fa que el mode
// degradat de §3.6 no passi per cap test funcional: es cobreix aquí.
//
// La clau és la pública de veritat del projecte de Supabase — publicar-la és
// tot el sentit d'un JWKS — i serveix de comprovació que ES256 es parseja.

/** @return array<array-key, mixed> */
function supabaseJwks(): array
{
    return ['keys' => [[
        'alg' => 'ES256',
        'crv' => 'P-256',
        'ext' => true,
        'key_ops' => ['verify'],
        'kid' => 'aa97e34a-06aa-456f-ae6d-ceea790dba1c',
        'kty' => 'EC',
        'use' => 'sig',
        'x' => 'gzZ82mIXYbRm_5EcrhVpeQ9j_rIj7_dWzN2QfAapDOg',
        'y' => 'QuY46i7RcOsZ-m6oQp7uFrS6JicZOi3YoMZhUERsowI',
    ]]];
}

/** @param list<array<array-key, mixed>|null> $responses null = la descàrrega falla */
function fetcherReturning(array $responses): JwksFetcherInterface
{
    return new class($responses) implements JwksFetcherInterface {
        private int $call = 0;

        /** @param list<array<array-key, mixed>|null> $responses */
        public function __construct(private readonly array $responses)
        {
        }

        /**
         * @return array<array-key, mixed>
         *
         * @throws JwksFetchFailedException
         */
        public function fetch(): array
        {
            $response = $this->responses[$this->call] ?? null;
            ++$this->call;

            return $response ?? throw new JwksFetchFailedException('unreachable');
        }
    };
}

it('parses the ES256 key out of the JWKS, indexed by kid', function (): void {
    $provider = new JwksKeyProvider(fetcherReturning([supabaseJwks()]), new ArrayAdapter(), new NullLogger(), 300);

    $keys = $provider->keys();

    expect($keys)->toHaveKey('aa97e34a-06aa-456f-ae6d-ceea790dba1c')
        ->and($keys['aa97e34a-06aa-456f-ae6d-ceea790dba1c']->getAlgorithm())->toBe('ES256');
});

it('keeps verifying with cached keys when the refresh fails', function (): void {
    // TTL 0: la còpia fresca ja neix caducada, així que la segona crida torna a
    // baixar-lo i es troba la xarxa caiguda. És l'escenari 10 del spec.
    $provider = new JwksKeyProvider(fetcherReturning([supabaseJwks(), null]), new ArrayAdapter(), new NullLogger(), 0);

    $provider->keys();

    expect($provider->keys())->toHaveKey('aa97e34a-06aa-456f-ae6d-ceea790dba1c');
});

it('does not re-fetch JWKS on every request while degraded', function (): void {
    // Després del fallback, `fresh` té TTL de gràcia: la tercera crida no ha
    // de tornar a pegar a la xarxa (la tercera resposta és null → petaria).
    $provider = new JwksKeyProvider(
        fetcherReturning([supabaseJwks(), null, null]),
        new ArrayAdapter(),
        new NullLogger(),
        0,
    );

    $provider->keys();
    $provider->keys();

    expect($provider->keys())->toHaveKey('aa97e34a-06aa-456f-ae6d-ceea790dba1c');
});

it('gives up when the fetch fails and no key was ever cached', function (): void {
    $provider = new JwksKeyProvider(fetcherReturning([null]), new ArrayAdapter(), new NullLogger(), 300);

    expect(fn () => $provider->keys())->toThrow(VerificationKeysUnavailableException::class);
});

it('refuses a JWKS that only carries symmetric keys', function (): void {
    // Un secret compartit permet FORJAR tokens, no només verificar-los: un
    // projecte encara amb el secret heretat ha de quedar-se sense claus, no
    // passar a verificar amb HS256.
    $symmetric = ['keys' => [['kty' => 'oct', 'alg' => 'HS256', 'kid' => 'legacy', 'k' => 'c2VjcmV0']]];
    $provider = new JwksKeyProvider(fetcherReturning([$symmetric]), new ArrayAdapter(), new NullLogger(), 300);

    expect(fn () => $provider->keys())->toThrow(VerificationKeysUnavailableException::class);
});
