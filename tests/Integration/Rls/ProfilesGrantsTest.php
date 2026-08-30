<?php

declare(strict_types=1);

use Doctrine\DBAL\Exception\DriverException;
use Tests\Integration\Kal\Infrastructure\Persistence\SupabaseConnection;

// `profiles` no té RLS ni cap política, o sigui que aquí no hi ha res a provar
// del `using`: l'única barrera que hi ha SÓN els grants. Per això el fitxer es
// diu Grants i no Policy.
//
// Venia amb el `TRUNCATE` heretat de l'`ALTER DEFAULT PRIVILEGES` per als dos
// rols del client, i és la taula de la qual pengen `kals.organizer_id` i
// `participations.user_id`: buidar-la amb CASCADE se'ls emporta tots dos.
// Durant un dia el que el va salvar va ser un accident — el CASCADE petava en
// arribar a `kals`, que sí que havia perdut el grant el 2026-08-29 —, no una
// decisió. Aquests tests fixen la decisió.
//
// COMPTE: en local `profiles` és un STUB (`20260716190425_profiles_stub.sql`);
// la taula de debò viu a la migració de Users del projecte REMOT. Aquests tests
// verifiquen la BD local i NOMÉS la local: quan hi hagi remot, els seus grants
// s'han de comprovar allà per separat.

beforeEach(function (): void {
    SupabaseConnection::begin();

    $this->connection = SupabaseConnection::get();
});

afterEach(function (): void {
    SupabaseConnection::rollBack();
});

it('refuses a truncate of the profiles from the client', function (): void {
    SupabaseConnection::asAuthenticatedRole();

    expect(fn () => $this->connection->executeStatement('TRUNCATE TABLE profiles CASCADE'))
        ->toThrow(DriverException::class, 'permission denied for table profiles');
});

// `anon` té els seus propis grants i la revocació del 2026-08-29 no el
// nomenava: cal provar-lo a part o el forat torna sense que ho vegi ningú.
it('refuses a truncate of the profiles from an anonymous caller', function (): void {
    SupabaseConnection::asAnonRole();

    expect(fn () => $this->connection->executeStatement('TRUNCATE TABLE profiles CASCADE'))
        ->toThrow(DriverException::class, 'permission denied for table profiles');
});
