<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Security;

use App\Shared\Domain\Security\AuthenticatedUserFinderInterface;
use App\Shared\Infrastructure\Symfony\Security\Exception\AuthenticationFailedException;
use App\Shared\Infrastructure\Symfony\Security\Exception\ExpiredTokenException;
use App\Shared\Infrastructure\Symfony\Security\Exception\InvalidTokenException;
use App\Shared\Infrastructure\Symfony\Security\Exception\ProfileNotFoundException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Les sis comprovacions de §3.3 del spec, totes obligatòries i sense cap camí
 * alternatiu: forma, signatura, `exp`, `iss`, `aud` i `sub`. Superades, resol
 * `sub` → `profiles.id` i el que travessa la frontera és el ULID intern.
 *
 * Cada fallada llença una excepció diferent a propòsit: Symfony les
 * col·lapsaria totes en una `BadCredentialsException` i el frontend no podria
 * distingir "refresca el token" de "torna a entrar".
 */
final readonly class SupabaseTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private JwksKeyProvider $keys,
        private AuthenticatedUserFinderInterface $users,
        private LoggerInterface $logger,
        private string $issuer,
        private string $audience,
    ) {
    }

    /** @throws AuthenticationFailedException */
    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        $claims = $this->verify($accessToken);
        $externalId = self::subject($claims);
        $identity = $this->users->findByExternalId($externalId);

        if (null === $identity) {
            // Es vol distingir als logs (spec §5, cas 8): el token era bo, o
            // sigui que o el trigger `handle_new_user()` ha fallat o el perfil
            // s'ha esborrat per RGPD. Cap de les dues és culpa del client.
            $this->logger->warning(
                'Valid Supabase token without a matching profile',
                ['external_id' => $externalId],
            );

            throw new ProfileNotFoundException();
        }

        return new UserBadge(
            $identity->id,
            static fn (): SupabaseUser => new SupabaseUser($identity),
        );
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws AuthenticationFailedException
     */
    private function verify(string $accessToken): array
    {
        $keys = $this->keys->keys();

        try {
            $claims = get_object_vars(JWT::decode($accessToken, $keys));
        } catch (ExpiredException $exception) {
            // Primer: ExpiredException estén UnexpectedValueException i el
            // catch de sota se l'empassaria com a "invàlid".
            throw new ExpiredTokenException(previous: $exception);
        } catch (\UnexpectedValueException|\DomainException|\InvalidArgumentException $exception) {
            // Malformat, `kid` desconegut, algorisme no suportat, signatura
            // tocada o `nbf`/`iat` en el futur.
            throw new InvalidTokenException(previous: $exception);
        }

        self::assertExpires($claims);
        $this->assertIssuer($claims);
        $this->assertAudience($claims);

        return $claims;
    }

    /**
     * `JWT::decode` només comprova `exp` si el claim hi és: un token sense
     * `exp` no caduca mai i passaria la validació sencera.
     *
     * @param array<array-key, mixed> $claims
     *
     * @throws InvalidTokenException
     */
    private static function assertExpires(array $claims): void
    {
        if (!isset($claims['exp'])) {
            throw new InvalidTokenException();
        }
    }

    /**
     * @param array<array-key, mixed> $claims
     *
     * @throws InvalidTokenException
     */
    private function assertIssuer(array $claims): void
    {
        if (($claims['iss'] ?? null) !== $this->issuer) {
            throw new InvalidTokenException();
        }
    }

    /**
     * @param array<array-key, mixed> $claims
     *
     * @throws InvalidTokenException
     */
    private function assertAudience(array $claims): void
    {
        $audience = $claims['aud'] ?? null;

        // `aud` pot ser una cadena o una llista; Supabase n'emet una de sola.
        if (!\in_array($this->audience, \is_array($audience) ? $audience : [$audience], true)) {
            throw new InvalidTokenException();
        }
    }

    /**
     * @param array<array-key, mixed> $claims
     *
     * @return non-empty-string
     *
     * @throws InvalidTokenException
     */
    private static function subject(array $claims): string
    {
        $subject = $claims['sub'] ?? null;

        if (!\is_string($subject) || '' === $subject) {
            throw new InvalidTokenException();
        }

        return $subject;
    }
}
