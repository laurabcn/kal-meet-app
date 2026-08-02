<?php

declare(strict_types=1);

namespace Tests\Kal\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;

/**
 * La connexió de la suite `db`: la Supabase local, amb l'esquema real que
 * `supabase db reset` aplica des de supabase/migrations/. No es crea cap taula
 * des dels tests a propòsit — un esquema propi per a tests tornaria a
 * permetre que el codi i les migracions se separessin sense que res ho digués.
 *
 * L'aïllament el fa la transacció: cada test n'obre una i sempre es desfà, o
 * sigui que la BD queda igual que estava i no cal netejar taules en cap ordre
 * concret (l'ordre de neteja amb FKs és un error que aquest projecte ja ha
 * comès una vegada). Com que `DbalKalRepository::create()` obre la seva pròpia
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

    /** El perfil que exigeix la FK `kals.organizer_id -> profiles.id`. */
    public static function insertProfile(string $id): void
    {
        self::get()->executeStatement(
            'INSERT INTO profiles (id, external_id) VALUES (:id, :external_id)',
            ['id' => $id, 'external_id' => 'ext-'.$id],
        );
    }
}
