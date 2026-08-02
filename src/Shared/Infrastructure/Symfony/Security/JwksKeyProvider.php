<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Security;

use App\Shared\Infrastructure\Symfony\Security\Exception\JwksFetchFailedException;
use App\Shared\Infrastructure\Symfony\Security\Exception\VerificationKeysUnavailableException;
use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException as CacheInvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Les claus públiques de Supabase, cachejades amb TTL i amb el comportament
 * degradat de §3.6 del spec: si la refresca falla però hi ha claus conegudes,
 * se segueix verificant amb elles — una caiguda de xarxa de mig minut no ha de
 * tombar totes les escriptures. Només en fred (cap clau enlloc) es rendeix.
 *
 * Conseqüència acceptada: durant la finestra de caché, una clau revocada a
 * Supabase encara es considera vàlida aquí.
 */
final readonly class JwksKeyProvider
{
    private const string CACHE_KEY_FRESH = 'shared.security.supabase_jwks.fresh';
    private const string CACHE_KEY_LAST_GOOD = 'shared.security.supabase_jwks.last_good';

    /** TTL curt de `fresh` mentre es verifica amb `last_good` — evita thundering herd. */
    private const int DEGRADED_GRACE_TTL_SECONDS = 60;

    public function __construct(
        private JwksFetcherInterface $fetcher,
        private CacheItemPoolInterface $cache,
        private LoggerInterface $logger,
        private int $ttlSeconds,
    ) {
    }

    /**
     * @return non-empty-array<string, Key>
     *
     * @throws VerificationKeysUnavailableException
     */
    public function keys(): array
    {
        $cached = $this->read(self::CACHE_KEY_FRESH);

        if (null !== $cached && null !== ($keys = self::parse($cached))) {
            return $keys;
        }

        try {
            $jwks = $this->fetcher->fetch();
            $keys = self::parse($jwks);

            if (null === $keys) {
                throw new JwksFetchFailedException('jwks_without_usable_keys');
            }
        } catch (JwksFetchFailedException $exception) {
            return $this->lastGoodKeys($exception);
        }

        $this->store($jwks);

        return $keys;
    }

    /**
     * @return non-empty-array<string, Key>
     *
     * @throws VerificationKeysUnavailableException
     */
    private function lastGoodKeys(JwksFetchFailedException $exception): array
    {
        $cached = $this->read(self::CACHE_KEY_LAST_GOOD);
        $keys = null === $cached ? null : self::parse($cached);

        if (null === $keys) {
            $this->logger->error(
                'Supabase JWKS unavailable and no cached keys to fall back on',
                ['error' => $exception->getMessage()],
            );

            throw new VerificationKeysUnavailableException();
        }

        $this->logger->warning(
            'Supabase JWKS refresh failed, verifying with cached keys',
            ['error' => $exception->getMessage()],
        );

        // Reinsereix `fresh` amb TTL de gràcia: sense això, cada request
        // tornaria a cridar el JWKS mentre la xarxa continua caiguda.
        $this->store($cached, self::DEGRADED_GRACE_TTL_SECONDS);

        return $keys;
    }

    /** @return array<array-key, mixed>|null */
    private function read(string $key): ?array
    {
        try {
            $value = $this->cache->getItem($key)->get();
        } catch (CacheInvalidArgumentException) {
            return null;
        }

        return \is_array($value) ? $value : null;
    }

    /** @param array<array-key, mixed> $jwks */
    private function store(array $jwks, ?int $freshTtlSeconds = null): void
    {
        try {
            $fresh = $this->cache->getItem(self::CACHE_KEY_FRESH);
            $lastGood = $this->cache->getItem(self::CACHE_KEY_LAST_GOOD);
        } catch (CacheInvalidArgumentException) {
            return;
        }

        // El TTL només caduca la còpia "fresca": la darrera bona no caduca mai,
        // és exactament el que sosté el mode degradat.
        $this->cache->save($fresh->set($jwks)->expiresAfter($freshTtlSeconds ?? $this->ttlSeconds));
        $this->cache->save($lastGood->set($jwks));
    }

    /**
     * @param array<array-key, mixed> $jwks
     *
     * @return non-empty-array<string, Key>|null
     */
    private static function parse(array $jwks): ?array
    {
        $entries = $jwks['keys'] ?? null;

        if (!\is_array($entries)) {
            return null;
        }

        $keys = [];

        foreach ($entries as $index => $entry) {
            if (!\is_array($entry) || !self::isAsymmetric($entry)) {
                continue;
            }

            $kid = $entry['kid'] ?? $index;

            try {
                $key = JWK::parseKey($entry);
            } catch (\InvalidArgumentException|\UnexpectedValueException|\DomainException) {
                continue;
            }

            if (null !== $key && (\is_string($kid) || \is_int($kid))) {
                $keys[(string) $kid] = $key;
            }
        }

        return [] === $keys ? null : $keys;
    }

    /**
     * Un JWKS amb claus simètriques no serveix per a res aquí: qui té el secret
     * pot forjar tokens, no només verificar-los. Es descarten abans de parsejar.
     *
     * @param array<array-key, mixed> $entry
     */
    private static function isAsymmetric(array $entry): bool
    {
        $kty = $entry['kty'] ?? null;
        $alg = $entry['alg'] ?? null;

        if (!\is_string($kty) || 'oct' === $kty) {
            return false;
        }

        return !\is_string($alg) || !str_starts_with($alg, 'HS');
    }
}
