<?php

declare(strict_types=1);

use App\Shared\Domain\ValueObject\UlidValue;
use Doctrine\DBAL\Exception\DriverException;
use Tests\Integration\Kal\Infrastructure\Persistence\SupabaseConnection;
use Tests\Integration\Rls\RlsFixture;

// Les guardes dels GRANTS de tot l'esquema `public`, no d'una política concreta.
// Tapa dues llacunes que va trobar la revisió de seguretat i que cap dels
// fitxers `*PolicyTest` veia:
//
//   (a) `asAnonRole()` només s'usava amb `TRUNCATE`. O sigui que CAP test
//       assertava que `anon` no pot fer `INSERT` ni `UPDATE`: si algú
//       reconcedís `INSERT` a `anon` sobre `kals`, tota la suite seguiria verda.
//       Els tests d'escriptura de `KalsPolicyTest` corren com a `authenticated`,
//       que és un rol amb els seus propis grants — i en aquesta mateixa sèrie de
//       migracions els dos ja han divergit dues vegades.
//
//   (b) Les guardes de `TRUNCATE` cobrien `kals`, `participations` i `profiles`.
//       Un `grant truncate on clues to authenticated` no el detectava res:
//       `TRUNCATE clues` és directe, no passa per `kals` i no depèn de cap
//       CASCADE que petés pel camí. Cinc de les vuit taules estaven descobertes.
//
// La invariant que fixen aquests tests, i que és la que declara
// `20260830154434_revoke_inherited_trigger_grants.sql`: **`anon` i
// `authenticated` no tenen sobre aquestes taules cap privilegi que no sigui
// `SELECT`.**

beforeEach(function (): void {
    SupabaseConnection::begin();

    $this->connection = SupabaseConnection::get();
});

afterEach(function (): void {
    SupabaseConnection::rollBack();
});

// El test barat que ho cobreix tot alhora: llegeix l'ACL de veritat en comptes
// d'intentar una operació. Un `grant` de qualsevol cosa (INSERT, TRUNCATE,
// TRIGGER, REFERENCES...) a qualsevol de les vuit taules el fa caure, també si
// arriba per l'`ALTER DEFAULT PRIVILEGES` que segueix viu i que farà néixer amb
// `Dxt` tota taula nova. Els tests de comportament de sota segueixen fent falta:
// aquest diu que el permís no hi és, no que Postgres el faci complir.
it('grants nothing but select to the client roles on the public tables', function (): void {
    $granted = $this->connection->fetchFirstColumn(
        <<<'SQL'
            SELECT grantee || ': ' || privilege_type || ' ON ' || table_name
            FROM information_schema.role_table_grants
            WHERE table_schema = 'public'
              AND grantee IN ('anon', 'authenticated')
              AND table_name = ANY(:tables)
              AND privilege_type <> 'SELECT'
            ORDER BY 1
            SQL,
        ['tables' => '{'.implode(',', RlsFixture::PUBLIC_TABLES).'}'],
    );

    // Llista formatada i no un array: en fallar, Pest imprimeix el rol, el
    // privilegi i la taula exactes en comptes d'un diff que s'ha d'anar a llegir.
    expect(implode(', ', $granted))->toBe(
        '',
        'Els rols del client han recuperat privilegis d\'escriptura sobre `public`. Recorda que l\'ALTER DEFAULT PRIVILEGES de Supabase segueix concedint Dxt a tota taula NOVA: si acabes de crear-ne una, li falta el `revoke`.',
    );
});

// Llacuna (a). `anon` és la visitant SENSE loguejar: que pugui escriure és
// estrictament pitjor que el cas d'`authenticated` que ja cobreix
// `KalsPolicyTest`, i fins ara no ho mirava ningú.
it('does not let an anonymous caller insert a kal', function (): void {
    SupabaseConnection::asAnonRole();

    expect(fn () => $this->connection->insert('kals', [
        'id' => UlidValue::generate()->value(),
        'organizer_id' => UlidValue::generate()->value(),
        'name' => 'Anonymous',
        'starts_on' => '2026-09-01 00:00:00',
        'invite_token' => UlidValue::generate()->value(),
    ]))->toThrow(DriverException::class, 'permission denied for table kals');
});

it('does not let an anonymous caller update a kal', function (): void {
    SupabaseConnection::asAnonRole();

    expect(fn () => $this->connection->executeStatement(
        'UPDATE kals SET name = :name',
        ['name' => 'Hijacked'],
    ))->toThrow(DriverException::class, 'permission denied for table kals');
});

// Llacuna (b). Les vuit taules, els dos rols: 16 casos que abans eren 6.
// `TRUNCATE` salta la RLS sencera, o sigui que aquí les polítiques no pinten
// res i el grant és l'única barrera que hi ha.
it('refuses a truncate of any public table from the client roles', function (string $role, string $table): void {
    'anon' === $role ? SupabaseConnection::asAnonRole() : SupabaseConnection::asAuthenticatedRole();

    expect(fn () => $this->connection->executeStatement("TRUNCATE TABLE {$table} CASCADE"))
        ->toThrow(DriverException::class, "permission denied for table {$table}");
})->with(['anon', 'authenticated'])->with(RlsFixture::PUBLIC_TABLES);
