<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Symfony\Security;

use App\Shared\Domain\Security\AuthenticatedUserFinderInterface;
use App\Shared\Domain\Security\AuthenticatedUserIdentity;
use App\Shared\Infrastructure\Symfony\Security\Exception\ExpiredTokenException;
use App\Shared\Infrastructure\Symfony\Security\Exception\InvalidTokenException;
use App\Shared\Infrastructure\Symfony\Security\Exception\JwksFetchFailedException;
use App\Shared\Infrastructure\Symfony\Security\Exception\ProfileNotFoundException;
use App\Shared\Infrastructure\Symfony\Security\Exception\VerificationKeysUnavailableException;
use App\Shared\Infrastructure\Symfony\Security\JwksFetcherInterface;
use App\Shared\Infrastructure\Symfony\Security\JwksKeyProvider;
use App\Shared\Infrastructure\Symfony\Security\SupabaseTokenHandler;
use App\Shared\Infrastructure\Symfony\Security\SupabaseUser;
use Firebase\JWT\JWT;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

// Els casos negatius del spec §5 (5 a 8) amb tokens construïts a propòsit.
// Aquest és el fitxer que el spec §9 demana: als tests funcionals el handler
// està substituït per un doble, i un handler que s'oblidés de comprovar `exp`,
// `iss` o `aud` passaria igualment tota la resta de la suite.
//
// La clau EC es genera en memòria a cada procés de test (mai al Git): signar
// tokens de mentida és exactament la seva feina.

const TEST_ISSUER = 'https://test-project.supabase.co/auth/v1';
const TEST_AUDIENCE = 'authenticated';
const TEST_KID = 'test-key-1';
const TEST_SUB = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
const TEST_USER_ID = '01J5M6XQBR4GTYHN8KZXP0F1W2';

/**
 * Parell EC P-256 efímer per al procés Pest. La privada no es versiona.
 *
 * @return array{private: string, jwks: array{keys: list<array<string, string>>}}
 */
function testKeyMaterial(): array
{
    static $material = null;

    if (null !== $material) {
        return $material;
    }

    $key = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name' => 'prime256v1',
    ]);

    if (false === $key) {
        throw new \RuntimeException('Unable to generate the ES256 test key');
    }

    if (!openssl_pkey_export($key, $privatePem)) {
        throw new \RuntimeException('Unable to export the ES256 test key');
    }

    $details = openssl_pkey_get_details($key);
    $x = $details['ec']['x'] ?? null;
    $y = $details['ec']['y'] ?? null;

    if (!\is_string($x) || !\is_string($y)) {
        throw new \RuntimeException('Unable to read the ES256 test key coordinates');
    }

    $material = [
        'private' => $privatePem,
        'jwks' => ['keys' => [[
            'kty' => 'EC',
            'crv' => 'P-256',
            'alg' => 'ES256',
            'use' => 'sig',
            'kid' => TEST_KID,
            'x' => rtrim(strtr(base64_encode($x), '+/', '-_'), '='),
            'y' => rtrim(strtr(base64_encode($y), '+/', '-_'), '='),
        ]]],
    ];

    return $material;
}

/** El JWKS que serviria Supabase per a la clau efímera del procés. */
function testJwks(): array
{
    return testKeyMaterial()['jwks'];
}

/** Els claims d'un token bo; cada test en trenca just un. */
function validClaims(): array
{
    return [
        'iss' => TEST_ISSUER,
        'aud' => TEST_AUDIENCE,
        'sub' => TEST_SUB,
        'exp' => time() + 3600,
        'iat' => time(),
    ];
}

function signToken(array $claims, ?string $key = null, string $kid = TEST_KID): string
{
    return JWT::encode($claims, $key ?? testKeyMaterial()['private'], 'ES256', $kid);
}

/** Un altre projecte de Supabase: mateix `kid`, clau que no és la nostra. */
function foreignPrivateKey(): string
{
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    openssl_pkey_export($key, $pem);

    return $pem;
}

function handlerFor(?AuthenticatedUserIdentity $identity, ?JwksFetcherInterface $fetcher = null): SupabaseTokenHandler
{
    $fetcher ??= new class implements JwksFetcherInterface {
        public function fetch(): array
        {
            return testJwks();
        }
    };

    $finder = new class($identity) implements AuthenticatedUserFinderInterface {
        public function __construct(private readonly ?AuthenticatedUserIdentity $identity)
        {
        }

        public function findByExternalId(string $externalId): ?AuthenticatedUserIdentity
        {
            return $this->identity;
        }
    };

    return new SupabaseTokenHandler(
        new JwksKeyProvider($fetcher, new ArrayAdapter(), new NullLogger(), 300),
        $finder,
        new NullLogger(),
        TEST_ISSUER,
        TEST_AUDIENCE,
    );
}

function handlerWithProfile(): SupabaseTokenHandler
{
    return handlerFor(new AuthenticatedUserIdentity(TEST_USER_ID, TEST_SUB));
}

// --- El camí feliç, i sobretot QUÈ travessa la frontera ---------------------

it('accepts a well-formed token and hands over the internal ULID, never the supabase uuid', function (): void {
    $badge = handlerWithProfile()->getUserBadgeFrom(signToken(validClaims()));

    $user = ($badge->getUserLoader())($badge->getUserIdentifier());

    expect($badge->getUserIdentifier())->toBe(TEST_USER_ID)
        ->and($user)->toBeInstanceOf(SupabaseUser::class)
        ->and($user->id())->toBe(TEST_USER_ID)
        ->and($user->externalId())->toBe(TEST_SUB);
});

it('accepts aud as a list that contains the expected audience', function (): void {
    // Supabase n'emet una de sola, però l'RFC permet la llista i un handler que
    // comparés amb === la rebutjaria.
    $token = signToken([...validClaims(), 'aud' => ['authenticated', 'another-app']]);

    expect(handlerWithProfile()->getUserBadgeFrom($token)->getUserIdentifier())->toBe(TEST_USER_ID);
});

// --- Cas 6: caducat, i distingible d'invàlid --------------------------------

it('rejects an expired token as expired, not as invalid', function (): void {
    $token = signToken([...validClaims(), 'exp' => time() - 10]);

    // La distinció és el contracte amb el frontend: "refresca el token" no és
    // el mateix que "torna a entrar".
    expect(fn () => handlerWithProfile()->getUserBadgeFrom($token))
        ->toThrow(ExpiredTokenException::class, 'Authentication token has expired.');
});

it('rejects a token with no exp claim at all', function (): void {
    // `JWT::decode` només mira `exp` si el claim hi és: sense aquesta
    // comprovació, un token sense `exp` no caducaria mai.
    $claims = validClaims();
    unset($claims['exp']);

    expect(fn () => handlerWithProfile()->getUserBadgeFrom(signToken($claims)))
        ->toThrow(InvalidTokenException::class, 'Authentication token is invalid.');
});

// --- Cas 5: la signatura ----------------------------------------------------

it('rejects a token whose payload was tampered with after signing', function (): void {
    [$header, $payload, $signature] = explode('.', signToken(validClaims()));
    $forged = rtrim(strtr(base64_encode((string) json_encode([...validClaims(), 'sub' => 'someone-else'])), '+/', '-_'), '=');

    expect(fn () => handlerWithProfile()->getUserBadgeFrom($header.'.'.$forged.'.'.$signature))
        ->toThrow(InvalidTokenException::class, 'Authentication token is invalid.');
});

it('rejects a token signed with a key that is not in the JWKS', function (): void {
    $token = signToken(validClaims(), foreignPrivateKey());

    expect(fn () => handlerWithProfile()->getUserBadgeFrom($token))
        ->toThrow(InvalidTokenException::class, 'Authentication token is invalid.');
});

it('rejects a token whose kid is unknown', function (): void {
    $token = signToken(validClaims(), kid: 'some-other-key');

    expect(fn () => handlerWithProfile()->getUserBadgeFrom($token))
        ->toThrow(InvalidTokenException::class, 'Authentication token is invalid.');
});

it('refuses an HS256 token forged with the public key as the secret', function (): void {
    // Confusió d'algorisme: la clau de verificació és pública per definició, o
    // sigui que si el handler acceptés l'`alg` del header qualsevol podria
    // forjar tokens. La clau del JWKS és ES256 i no ha de valer per a HS256.
    $header = rtrim(strtr(base64_encode((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT', 'kid' => TEST_KID])), '+/', '-_'), '=');
    $payload = rtrim(strtr(base64_encode((string) json_encode(validClaims())), '+/', '-_'), '=');
    $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', $header.'.'.$payload, (string) json_encode(testJwks()['keys'][0]), true)), '+/', '-_'), '=');

    expect(fn () => handlerWithProfile()->getUserBadgeFrom($header.'.'.$payload.'.'.$signature))
        ->toThrow(InvalidTokenException::class, 'Authentication token is invalid.');
});

it('rejects something that is not a JWT at all', function (): void {
    expect(fn () => handlerWithProfile()->getUserBadgeFrom('not-a-jwt'))
        ->toThrow(InvalidTokenException::class, 'Authentication token is invalid.');
});

// --- Cas 7: token d'un altre projecte, ben signat pel seu emissor -----------

it('rejects a token issued by another supabase project', function (): void {
    $token = signToken([...validClaims(), 'iss' => 'https://another-project.supabase.co/auth/v1']);

    expect(fn () => handlerWithProfile()->getUserBadgeFrom($token))
        ->toThrow(InvalidTokenException::class, 'Authentication token is invalid.');
});

it('rejects a token with no iss claim', function (): void {
    $claims = validClaims();
    unset($claims['iss']);

    expect(fn () => handlerWithProfile()->getUserBadgeFrom(signToken($claims)))
        ->toThrow(InvalidTokenException::class, 'Authentication token is invalid.');
});

it('rejects a token meant for another audience', function (): void {
    $token = signToken([...validClaims(), 'aud' => 'service-role']);

    expect(fn () => handlerWithProfile()->getUserBadgeFrom($token))
        ->toThrow(InvalidTokenException::class, 'Authentication token is invalid.');
});

it('rejects a token with no aud claim', function (): void {
    $claims = validClaims();
    unset($claims['aud']);

    expect(fn () => handlerWithProfile()->getUserBadgeFrom(signToken($claims)))
        ->toThrow(InvalidTokenException::class, 'Authentication token is invalid.');
});

// --- El subject -------------------------------------------------------------

it('rejects a token with an empty sub', function (): void {
    expect(fn () => handlerWithProfile()->getUserBadgeFrom(signToken([...validClaims(), 'sub' => ''])))
        ->toThrow(InvalidTokenException::class, 'Authentication token is invalid.');
});

it('rejects a token with no sub claim', function (): void {
    $claims = validClaims();
    unset($claims['sub']);

    expect(fn () => handlerWithProfile()->getUserBadgeFrom(signToken($claims)))
        ->toThrow(InvalidTokenException::class, 'Authentication token is invalid.');
});

// --- Cas 8: token bo, perfil que no hi és ----------------------------------

it('rejects a valid token whose sub has no profile row', function (): void {
    // Ni el trigger handle_new_user() ha fet la seva feina, ni el perfil hi és
    // per RGPD: es distingeix del token invàlid perquè no és culpa del client.
    expect(fn () => handlerFor(null)->getUserBadgeFrom(signToken(validClaims())))
        ->toThrow(ProfileNotFoundException::class, 'User profile was not found.');
});

// --- Cas 11: sense claus de verificació ------------------------------------

it('gives up with the keys-unavailable failure when the JWKS cannot be reached', function (): void {
    $unreachable = new class implements JwksFetcherInterface {
        public function fetch(): array
        {
            throw new JwksFetchFailedException('unreachable');
        }
    };

    // No és un 401: el token pot ser perfectament bo i el problema és nostre.
    expect(fn () => handlerFor(new AuthenticatedUserIdentity(TEST_USER_ID, TEST_SUB), $unreachable)->getUserBadgeFrom(signToken(validClaims())))
        ->toThrow(VerificationKeysUnavailableException::class);
});
