-- Migration: soft delete també a les filles del KAL
--
-- Fins ara `deleted_at` només existia a `kals`, i esborrar un KAL deixava
-- clues, meetings, files, locales i debate_rooms sense marcar. No eren
-- visibles (tot passa per `is_kal_member` / `is_kal_organizer`, que ja donen
-- fals quan el KAL està esborrat), però la marca sí que faltava a la fila.
--
-- Decisió del 2026-08-11: el DELETE del KAL marca tot l'arbre a la mateixa
-- transacció. Conseqüència a tenir present si algun dia es restaura un KAL:
-- `deleted_at` de les filles no distingeix qui l'ha posat — la cascada o un
-- esborrat individual anterior — o sigui que restaurar demanarà criteri propi
-- (guardar la marca de la cascada, o acceptar que tot torna alhora).
--
-- Les policies de SELECT/UPDATE passen a excloure les files marcades: afecta
-- només el client directe (frontend amb RLS). El backend va amb service_role
-- i salta RLS, per això el filtre també viu al repositori.

alter table kal_locales  add column if not exists deleted_at timestamptz;
alter table kal_files    add column if not exists deleted_at timestamptz;
alter table clues        add column if not exists deleted_at timestamptz;
alter table meetings     add column if not exists deleted_at timestamptz;
alter table debate_rooms add column if not exists deleted_at timestamptz;

comment on column kal_locales.deleted_at  is 'Soft delete, cascaded from the parent kal. RLS excludes rows with non-null deleted_at.';
comment on column kal_files.deleted_at    is 'Soft delete, cascaded from the parent kal. RLS excludes rows with non-null deleted_at.';
comment on column clues.deleted_at        is 'Soft delete, cascaded from the parent kal. RLS excludes rows with non-null deleted_at.';
comment on column meetings.deleted_at     is 'Soft delete, cascaded from the parent kal. RLS excludes rows with non-null deleted_at.';
comment on column debate_rooms.deleted_at is 'Soft delete, cascaded from the parent kal. RLS excludes rows with non-null deleted_at.';

-- Una pista esborrada no està «alliberada»: sense això, la reunió d'una pista
-- esborrada individualment (CRUD de pistes, encara per fer) seguiria essent
-- visible mentre la reunió no estigués marcada ella mateixa.
create or replace function is_clue_released(p_clue_id text)
returns boolean
language sql
stable
security definer
set search_path = ''
as $$
    select exists (
        select 1
        from public.clues c
        where c.id = p_clue_id
          and c.deleted_at is null
          and c.starts_on <= now()
    );
$$;

-- kal_locales
drop policy if exists kal_locales_select_member on kal_locales;
create policy kal_locales_select_member on kal_locales
    for select using (
        deleted_at is null
        and is_kal_member(kal_id)
    );

-- kal_files
drop policy if exists kal_files_select_member on kal_files;
create policy kal_files_select_member on kal_files
    for select using (
        deleted_at is null
        and is_kal_member(kal_id)
    );

-- clues
drop policy if exists clues_select_member on clues;
create policy clues_select_member on clues
    for select using (
        deleted_at is null
        and is_kal_member(kal_id)
        and is_clue_released(id)
    );

drop policy if exists clues_select_organizer on clues;
create policy clues_select_organizer on clues
    for select using (
        deleted_at is null
        and is_kal_organizer(kal_id)
    );

drop policy if exists clues_update_organizer on clues;
create policy clues_update_organizer on clues
    for update using (
        deleted_at is null
        and is_kal_organizer(kal_id)
    )
    with check (is_kal_organizer(kal_id));

-- meetings
drop policy if exists meetings_select_member on meetings;
create policy meetings_select_member on meetings
    for select using (
        deleted_at is null
        and is_kal_member(kal_id)
        and (clue_id is null or is_clue_released(clue_id))
    );

drop policy if exists meetings_select_organizer on meetings;
create policy meetings_select_organizer on meetings
    for select using (
        deleted_at is null
        and is_kal_organizer(kal_id)
    );

drop policy if exists meetings_update_organizer on meetings;
create policy meetings_update_organizer on meetings
    for update using (
        deleted_at is null
        and is_kal_organizer(kal_id)
    )
    with check (is_kal_organizer(kal_id));

-- debate_rooms
drop policy if exists debate_rooms_select_member on debate_rooms;
create policy debate_rooms_select_member on debate_rooms
    for select using (
        deleted_at is null
        and is_kal_member(kal_id)
    );
