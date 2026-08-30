-- Migration: l'escriptura de l'agregat Kal passa NOMÉS pel backend
--
-- Decisió del 2026-08-29. Fins ara l'esquema deixava dues portes obertes a la
-- mateixa habitació: el backend (service_role) escrivia els KALs, però el
-- client amb supabase-js TAMBÉ podia fer-ho — tenia grants d'INSERT/UPDATE i
-- unes polítiques `*_organizer` que ho autoritzaven. Cap client feia servir
-- aquesta segona porta (zero `.insert()`/`.update()`/`.upsert()`/`.delete()` a
-- tot el `src/` del frontend), però existia.
--
-- Es tanca perquè les invariants de l'agregat viuen al domini PHP, no a
-- l'esquema: que les dates de cada clue caiguin dins del rang del Kal, que el
-- `locale` d'una pista sigui un dels habilitats al KAL, que esborrar un KAL
-- marqui tot l'arbre a la mateixa transacció. Un INSERT directe del client se
-- les salta totes i deixa files que el repositori no sap rehidratar. La RLS sap
-- dir QUI, no sap dir SI ÉS COHERENT.
--
-- Es revoquen els grants I es dropen les policies d'escriptura, no només una
-- de les dues coses. Deixar les policies com a «segona barrera» seria codi mort
-- que enganya qui llegeix l'esquema: semblaria que el client hi pot escriure
-- quan el grant ja ho impedeix abans que cap `with check` s'arribi a avaluar.
-- L'esquema ha de dir la veritat sobre qui escriu.
--
-- El backend no se n'assabenta, però no pel motiu que semblaria: aquí no hi ha
-- cap service_role key pel mig. La connexió de Doctrine DBAL entra com a rol
-- `postgres` (`DATABASE_URL` a `.env`, `TEST_DATABASE_URL` a `.env.test`), que
-- és el PROPIETARI d'aquestes taules, i és la propietat —no cap bypass— el que
-- el deixa fora d'un `revoke` sobre `anon`/`authenticated`. Que consti, perquè
-- la confusió és fàcil i cara: a Postgres cap rol salta els grants (`service_role`
-- té `rolbypassrls`, que és la RLS i prou; RLS i grants són mecanismes
-- diferents), i la *service_role key* és una credencial de l'API REST de
-- Supabase (PostgREST, supabase-js) que aquest backend no fa servir enlloc. De
-- fet `service_role` no té ni `SELECT` sobre aquestes taules: qui «arregli» el
-- backend perquè hi passi es trobarà `permission denied for table …`.
-- Aquesta migració NO toca res de lectura — els `grant select` i totes les
-- polítiques `*_select_*` es queden exactament igual, perquè el frontend
-- segueix llegint directament amb RLS.
--
-- Canvi de comportament a tenir present als tests: abans, un UPDATE que no
-- passava el `using` no petava, només afectava 0 files. Ara, sense el grant,
-- Postgres talla abans d'avaluar la RLS i llença `permission denied for table
-- <taula>` — igual que ja passava amb `participations` i `debate_rooms`.

-- -----------------------------------------------------------------------------
-- 1. Grants
-- -----------------------------------------------------------------------------
-- S'hi inclou TRUNCATE, que no era una decisió de ningú: ve de l'ALTER DEFAULT
-- PRIVILEGES de `postgres` sobre `public` (concedeix Dxt — TRUNCATE, REFERENCES,
-- TRIGGER) i el van heretar totes les taules d'aquestes migracions. TRUNCATE
-- **salta la RLS sencera**, o sigui que qualsevol usuària loguejada podia
-- buidar l'agregat amb un `truncate kals cascade`. Verificat contra la BD local
-- abans d'escriure això, no deduït.
--
-- DELETE no s'ha concedit mai (aquí tot és soft delete via `deleted_at`); es
-- revoca igualment perquè la llista digui la invariant sencera: `authenticated`
-- no té CAP privilegi d'escriptura sobre l'agregat.
revoke insert, update, delete, truncate on
    kals,
    kal_locales,
    kal_files,
    clues,
    meetings,
    debate_rooms
from authenticated;

-- -----------------------------------------------------------------------------
-- 2. Policies d'escriptura
-- -----------------------------------------------------------------------------
-- Les vuit polítiques `*_insert_organizer` / `*_update_organizer`. Unes quantes
-- estaven redefinides en migracions posteriors (`20260805085005` per a
-- `kals_insert_organizer`; `20260811064206` per a les de `clues` i `meetings`);
-- el `drop policy` no distingeix versions, cau la que hi hagi.
drop policy if exists kals_insert_organizer on kals;
drop policy if exists kals_update_organizer on kals;
drop policy if exists kal_locales_insert_organizer on kal_locales;
drop policy if exists kal_files_insert_organizer on kal_files;
drop policy if exists clues_insert_organizer on clues;
drop policy if exists clues_update_organizer on clues;
drop policy if exists meetings_insert_organizer on meetings;
drop policy if exists meetings_update_organizer on meetings;

-- Les taules es queden amb RLS activa i només polítiques de `select`. Una taula
-- amb RLS i cap política per a un comandament és denegació per defecte, o sigui
-- que l'escriptura del client queda tancada per partida doble: primer el grant,
-- després la RLS.

comment on table kals is 'Kal aggregate root. Read-only for `authenticated`: every write goes through the backend (service_role), which is where the aggregate invariants live.';
comment on table clues is 'Clue entity of the Kal aggregate. Read-only for `authenticated`; writes go through the backend.';
comment on table meetings is 'Meeting entity of the Kal aggregate. Read-only for `authenticated`; writes go through the backend.';
comment on table kal_locales is 'Locales enabled on a Kal. Read-only for `authenticated`; writes go through the backend.';
comment on table kal_files is 'Pattern files of a Kal. Read-only for `authenticated`; writes go through the backend.';

-- `is_own_profile` (creada a `20260805085005` per a `kals_insert_organizer`) es
-- queda: ja no la fa servir cap política, però és un helper `security definer`
-- correcte i barat, i l'aula de participant en pot necessitar un d'igual. Si
-- quan s'especifiqui l'aula segueix sense fer-se servir, que caigui llavors.
