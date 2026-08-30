-- Migration: es tanca el TRUNCATE heretat que va quedar fora d'ahir
--
-- Continuació directa de `20260829210457_lock_kal_writes_to_backend.sql`, que
-- va tancar `TRUNCATE` sobre les sis taules de l'agregat Kal però NOMÉS per al
-- rol `authenticated`. L'inventari complet, fet ara contra la BD local, diu que
-- el forat era més gran que el que es va tapar:
--
--   * `participations` i `profiles` conserven `TRUNCATE` per a `authenticated`
--     (van quedar fora de la llista d'ahir)
--   * `anon` conserva `TRUNCATE` sobre LES VUIT taules, incloses les sis que
--     ahir es van «arreglar»: la revocació d'ahir no nomenava aquest rol
--
-- D'ON VE. De ningú. És l'`ALTER DEFAULT PRIVILEGES` de `postgres` sobre
-- l'esquema `public` que munta Supabase, que concedeix `Dxt` (TRUNCATE,
-- REFERENCES, TRIGGER) a `anon`, `authenticated` i `service_role` sobre tota
-- taula nova. Cap migració d'aquest repo va escriure mai aquests grants: els
-- van heretar totes les taules pel sol fet de crear-se.
--
-- PER QUÈ TRUNCATE ÉS EL GREU DELS TRES. Perquè **salta la RLS sencera**: no
-- l'avalua i la filtra, se la salta. Un `DELETE` sense política afecta zero
-- files; un `TRUNCATE` buida la taula tant si hi ha polítiques com si no. O
-- sigui que tota la feina de `*_select_*`, `is_kal_member` i el soft delete no
-- hi pinta res — i, a sobre, `TRUNCATE` no és soft delete: aquí no s'esborra
-- mai res de veritat, i això ho esborraria de veritat.
--
-- PER QUÈ EL BACKEND NO SE N'ASSABENTA. Perquè no passa per aquests rols: la
-- connexió DBAL va com a `postgres` (propietari de les taules, `bypassrls`), i
-- la superfície Supabase va amb la service_role key. Revocar-li privilegis a
-- `anon`/`authenticated` no li toca res. Igual que ahir, aquesta migració NO
-- toca cap `grant select` ni cap política: el frontend segueix llegint directe.
--
-- ABAST REAL, per no vendre-ho més gros del que és: `anon` i `authenticated`
-- són rols NOLOGIN. Ningú s'hi connecta; només s'hi arriba pel `set role` que fa
-- PostgREST després de validar el JWT, i PostgREST no emet `TRUNCATE`. O sigui
-- que no és un exploit d'una línia des del navegador: és la xarxa de sota que
-- ha d'aguantar el dia que hi hagi una funció RPC, una injecció o una consola
-- SQL amb aquests rols. Es tanca pel mateix motiu que ahir — que l'esquema
-- digui la veritat sobre qui pot escriure.

-- -----------------------------------------------------------------------------
-- 1. `participations` i `profiles` — el que va quedar fora d'ahir
-- -----------------------------------------------------------------------------
-- Comprovat abans d'escriure-ho: de la resta de privilegis d'escriptura no en
-- tenien cap (`participations` només `SELECT`; `profiles`, ni això). Es
-- nomenen igualment, com ahir, perquè la llista digui la invariant sencera i no
-- calgui anar a mirar l'ACL per saber-la: cap dels dos rols del client escriu
-- aquí.
revoke insert, update, delete, truncate on
    participations,
    profiles
from anon, authenticated;

-- ATENCIÓ amb `profiles`: en local és un STUB. `20260716190425_profiles_stub.sql`
-- ja ho diu — la taula de veritat (amb `generate_ulid()`, el trigger
-- `handle_new_user()` i la resta de columnes) viu a la migració de Users del
-- projecte REMOT, que no és en aquest repo. O sigui que aquest `revoke` tanca el
-- forat a la BD local i NOMÉS a la BD local.
--
-- Quan hi hagi projecte remot linkat, s'ha de comprovar allà PER SEPARAT: la
-- `profiles` de debò es va crear amb el mateix `ALTER DEFAULT PRIVILEGES`, o
-- sigui que hi haurà heretat el mateix `Dxt` i aquesta migració no l'hi haurà
-- tocat. Que no se n'oblidi ningú: la comanda per mirar-ho és
--   select grantee, privilege_type from information_schema.role_table_grants
--   where table_schema='public' and table_name='profiles';
-- i el que hi ha de faltar és TRUNCATE per a `anon` i `authenticated`.

-- -----------------------------------------------------------------------------
-- 2. `anon` sobre l'agregat Kal — el rol que ahir no es va nomenar
-- -----------------------------------------------------------------------------
-- La revocació d'ahir deia `from authenticated` i prou, així que aquestes sis
-- taules encara deixen que `anon` les buidi. `anon` és el rol de la visitant
-- SENSE loguejar: és estrictament pitjor que el cas que ahir es va considerar
-- prou greu per tancar.
revoke insert, update, delete, truncate on
    kals,
    kal_locales,
    kal_files,
    clues,
    meetings,
    debate_rooms
from anon;

-- -----------------------------------------------------------------------------
-- 3. El que aquesta migració deliberadament NO fa
-- -----------------------------------------------------------------------------
-- (a) `REFERENCES` i `TRIGGER` es queden. Vénen del mateix `Dxt` heretat i
--     tampoc els va decidir ningú. `REFERENCES` és inert (cal CREATE sobre
--     l'esquema per tenir una taula on posar la FK, i cap dels dos rols en té),
--     però `TRIGGER` NO ho és: `create trigger` no demana CREATE sobre
--     l'esquema, i s'ha verificat que `authenticated` pot enganxar un trigger a
--     `kals`. Es deixa fora perquè és la mateixa decisió d'abast que el punt
--     (b), no una altra: o es tanquen tots dos rols a totes les taules, o no es
--     toca. Va a la taula de la desenvolupadora, no aquí.
--
-- (b) L'`ALTER DEFAULT PRIVILEGES` segueix igual, o sigui que **la propera
--     taula que creï una migració tornarà a néixer amb `Dxt` per als tres
--     rols**. Aquesta migració tapa les vuit taules d'avui, no la font. Tancar
--     la font és una decisió a part.

comment on table participations is 'Participation rows. Read-only for `authenticated` (write goes through the backend, which validates the invite token); no privileges at all for `anon`.';
