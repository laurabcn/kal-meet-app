<?php

declare(strict_types=1);

namespace Tests\Integration\Kal\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;

/**
 * La connexió de la suite `integration`: la Supabase local, amb l'esquema real que
 * `supabase db reset` aplica des de supabase/migrations/. No es crea cap taula
 * des dels tests a propòsit — un esquema propi per a tests tornaria a
 * permetre que el codi i les migracions se separessin sense que res ho digués.
 *
 * L'aïllament el fa la transacció: cada test n'obre una i sempre es desfà, o
 * sigui que la BD queda igual que estava i no cal netejar taules en cap ordre
 * concret (l'ordre de neteja amb FKs és un error que aquest projecte ja ha
 * comès una vegada). Com que `KalRepository::create()` obre la seva pròpia
 * transacció, cal `setNestTransactionsWithSavepoints`: sense això, el seu
 * `commit()` tancaria la del test i el rollback no desfaria res.
 */
final class SupabaseConnection
{
    private static ?Connection $connection = null;

    public static function get(): Connection
    {
        if (null !== self::$connection) {
            return self::$connection;
        }

        $dsn = $_ENV['TEST_DATABASE_URL'] ?? null;

        if (!\is_string($dsn) || '' === $dsn) {
            throw new \RuntimeException('TEST_DATABASE_URL no està definida: la suite `db` necessita la Supabase local (.env.test).');
        }

        // El DsnParser no coneix cap esquema per defecte: `postgresql://` és el
        // que escriuen Symfony i Supabase, i sense el mapa peta amb UnknownDriver.
        $parser = new DsnParser(['postgres' => 'pdo_pgsql', 'postgresql' => 'pdo_pgsql']);
        $connection = DriverManager::getConnection($parser->parse($dsn));

        try {
            $connection->executeQuery('SELECT 1');
        } catch (\Throwable $e) {
            // Fallar, no saltar-se'ls: si has corregut `make test-db` és que
            // vols aquests tests, i un skip silenciós els deixaria podrir-se.
            throw new \RuntimeException(
                'No es pot connectar a la Supabase local. Arrenca-la amb `supabase start` (i `supabase db reset` el primer cop).',
                0,
                $e,
            );
        }

        $connection->setNestTransactionsWithSavepoints(true);

        return self::$connection = $connection;
    }

    public static function begin(): void
    {
        self::get()->beginTransaction();
    }

    public static function rollBack(): void
    {
        $connection = self::get();

        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
    }

    /**
     * El perfil que exigeix la FK `kals.organizer_id -> profiles.id`.
     *
     * L'`external_id` només importa als tests de RLS: les polítiques comparen
     * `profiles.external_id` amb `auth.uid()::text`, que és un uuid. Per la
     * resta de tests un valor qualsevol serveix.
     */
    public static function insertProfile(string $id, ?string $externalId = null): void
    {
        self::get()->executeStatement(
            'INSERT INTO profiles (id, external_id) VALUES (:id, :external_id)',
            ['id' => $id, 'external_id' => $externalId ?? 'ext-'.$id],
        );
    }

    /**
     * Qui és `auth.uid()` durant la transacció del test; `null` = anònima.
     *
     * `set_config(..., true)` és local a la transacció, o sigui que el rollback
     * de l'`afterEach` també desfà la identitat.
     *
     * @throws \JsonException
     */
    public static function authenticateAs(?string $externalId): void
    {
        self::get()->executeStatement(
            'SELECT set_config(:setting, :claims, true)',
            [
                'setting' => 'request.jwt.claims',
                'claims' => null === $externalId
                    ? ''
                    : json_encode(['sub' => $externalId], \JSON_THROW_ON_ERROR),
            ],
        );
    }

    /**
     * Passa al rol `authenticated`, el que Supabase dona a una usuària
     * loguejada. Imprescindible per provar una política: `postgres` és
     * superusuari i **salta la RLS**, o sigui que sense això una política
     * sembla que funciona encara que no filtri res. `SET LOCAL` es desfà amb
     * la transacció.
     */
    public static function asAuthenticatedRole(): void
    {
        self::get()->executeStatement('SET LOCAL ROLE authenticated');
    }
}
