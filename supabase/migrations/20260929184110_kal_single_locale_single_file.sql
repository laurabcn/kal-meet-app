-- Migration: un KAL té un sol idioma i, com a molt, un fitxer de patró
--
-- Canvi de política (decidit 2026-09-29): un KAL deixa de ser un contenidor
-- multi-idioma. Té UN idioma, fix des de la creació, i les pistes i els
-- fitxers l'hereten — per això desapareixen `clues.locale`,
-- `clues.file_locale` i `kal_files.locale`: amb un sol idioma per KAL, un
-- locale a la filla només pot ser redundant o contradictori.
--
-- - `kal_locales` → columna `kals.locale`. Una taula 1:N per guardar un sol
--   valor no aporta res, i amb ella se'n va la seva policy de select.
-- - `kal_files` es queda com a taula (conserva la forma per a la futura
--   extracció de Pattern), però amb 0..1 fitxer viu per KAL: índex únic
--   parcial sobre `kal_id` on `deleted_at is null`, perquè els soft deletes
--   no bloquegin pujar-ne un de nou.
--
-- NO és endavant-compatible (el codi anterior llegeix `kal_locales` i les
-- columnes esborrades). S'accepta perquè encara no hi ha cap entorn
-- desplegat: codi i esquema van al mateix canvi.

-- =============================================================================
-- kals.locale
-- =============================================================================
alter table kals add column if not exists locale text;

-- Backfill: dels KALs que en tenien més d'un, es queda el primer
-- alfabèticament (només hi ha dades locals de prova).
update kals k
set locale = (
    select min(l.locale)
    from kal_locales l
    where l.kal_id = k.id
)
where k.locale is null;

alter table kals alter column locale set not null;

alter table kals drop constraint if exists kals_locale_iso;
alter table kals add constraint kals_locale_iso check (locale ~ '^[a-z]{2}$');

comment on column kals.locale is 'ISO 639-1 language of the KAL. One per KAL, fixed at creation; clues and files inherit it.';

drop table if exists kal_locales;

-- =============================================================================
-- kal_files: 0..1 fitxer viu per KAL, sense locale propi
-- =============================================================================
alter table kal_files drop constraint if exists kal_files_locale_iso;
alter table kal_files drop column if exists locale;

create unique index if not exists kal_files_kal_id_active_unique
    on kal_files (kal_id)
    where deleted_at is null;

comment on table kal_files is 'Pattern file of a KAL, at most one active per KAL (storage bucket: kal-patterns). Language inherited from kals.locale.';

-- =============================================================================
-- clues: sense locale propi
-- =============================================================================
alter table clues drop constraint if exists clues_locale_iso;
alter table clues drop constraint if exists clues_file_locale_iso;
alter table clues drop column if exists locale;
alter table clues drop column if exists file_locale;
