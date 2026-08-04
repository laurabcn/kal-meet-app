<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Symfony\Security;

use App\Shared\Domain\Security\AuthenticatedUserIdentity;
use App\Shared\Infrastructure\Symfony\Security\Exception\ExpiredTokenException;
use App\Shared\Infrastructure\Symfony\Security\Exception\InvalidTokenException;
use App\Shared\Infrastructure\Symfony\Security\Exception\ProfileNotFoundException;
use App\Shared\Infrastructure\Symfony\Security\Exception\VerificationKeysUnavailableException;
use App\Shared\Infrastructure\Symfony\Security\SupabaseUser;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Substitueix el SupabaseTokenHandler a l'entorn de test (alias a
 * config/services_test.yaml): cap test funcional surt a la xarxa a buscar el
 * JWKS ni toca `profiles`.
 *
 * Els tokens màgics de sota no verifiquen res: només fan arribar cada fallada
 * al firewall per comprovar que en surt l'estat i el codi de §3.5 del spec.
 * Que cada fallada es produeixi QUAN TOCA — signatura tocada, `exp`, `iss`,
 * `aud` — és feina de SupabaseTokenHandlerTest, que signa tokens de veritat
 * contra el handler real.
 */
final readonly class StubTokenHandler implements AccessTokenHandlerInterface
{
    public const string TOKEN = 'valid-test-token';

    // El mateix ULID que fan servir els payloads de KalCreateControllerTest:
    // l'organizerId dels KALs que s'hi creen surt d'aquí (spec §3.4).
    public const string USER_ID = '01J5M6XQBR4GTYHN8KZXP0F1W2';
    public const string EXTERNAL_ID = 'b3f1c9d2-7a54-4e8b-9c10-2d6f5a8e4b71';

    public const string EXPIRED_TOKEN = 'expired-test-token';
    public const string NO_PROFILE_TOKEN = 'no-profile-test-token';
    public const string KEYS_DOWN_TOKEN = 'keys-down-test-token';

    /**
     * @throws ExpiredTokenException
     * @throws InvalidTokenException
     * @throws ProfileNotFoundException
     * @throws VerificationKeysUnavailableException
     */
    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        match ($accessToken) {
            self::TOKEN => null,
            self::EXPIRED_TOKEN => throw new ExpiredTokenException(),
            self::NO_PROFILE_TOKEN => throw new ProfileNotFoundException(),
            self::KEYS_DOWN_TOKEN => throw new VerificationKeysUnavailableException(),
            default => throw new InvalidTokenException(),
        };

        $identity = new AuthenticatedUserIdentity(self::USER_ID, self::EXTERNAL_ID);

        return new UserBadge(
            $identity->id,
            static fn (): SupabaseUser => new SupabaseUser($identity),
        );
    }
}
