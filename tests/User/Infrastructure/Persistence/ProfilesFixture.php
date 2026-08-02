<?php

declare(strict_types=1);

namespace Tests\User\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

/**
 * Harness mínim per a l'adaptador: SQLite en memòria amb la mateixa forma que
 * `profiles` al stub (`supabase/migrations/20260716190425_profiles_stub.sql`).
 *
 * Deliberadament NO reprodueix el CHECK `length(id) = 26` de Postgres: cal
 * poder-hi desar un id corrupte per provar el camí d'error de l'adaptador.
 * Prova el SQL i la hidratació de veritat, no l'esquema real ni les RLS.
 */
final class ProfilesFixture
{
    public static function connection(): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        $connection->executeStatement(
            <<<'SQL'
                CREATE TABLE profiles (
                    id          TEXT NOT NULL PRIMARY KEY,
                    external_id TEXT NOT NULL UNIQUE
                )
                SQL,
        );

        return $connection;
    }

    public static function insert(Connection $connection, string $id, string $externalId): void
    {
        $connection->executeStatement(
            'INSERT INTO profiles (id, external_id) VALUES (:id, :external_id)',
            ['id' => $id, 'external_id' => $externalId],
        );
    }
}
