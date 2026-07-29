-- Migration: Kal MVP schema
-- Creates tables for the Kal aggregate write path (CreateKal):
--   kals, kal_locales, kal_files, clues, meetings, debate_rooms
--
-- Dependencies:
--   - profiles.id (ULID text 26) must exist before enforcing organizer_id FK.
--     For local dev: 20260716190425_profiles_stub.sql creates a minimal
--     profiles table. In the real Supabase project, the full profiles table
--     comes from the Users ticket migration.
--   - participations table does NOT exist yet; RLS helpers that need it are
--     marked as stubs and will be updated by the Participation ticket.

-- =============================================================================
-- kals
-- =============================================================================
create table if not exists kals (
    id           text    not null primary key,
    organizer_id text    not null,
    name         text    not null,
    description  text,
    starts_on    timestamptz not null,
    ends_on      timestamptz,
    cover_path   text,
    invite_token text    not null,
    deleted_at   timestamptz,
    created_at   timestamptz not null default now(),
    updated_at   timestamptz not null default now(),

    constraint kals_id_length         check (length(id) = 26),
    constraint kals_organizer_id_len  check (length(organizer_id) = 26),
    constraint kals_name_not_blank    check (trim(name) <> ''),
    constraint kals_invite_token_not_blank check (trim(invite_token) <> ''),
    constraint kals_date_range        check (ends_on is null or ends_on > starts_on)
);

create unique index if not exists kals_invite_token_unique
    on kals (invite_token);

-- FK to profiles (organizer must be a known user).
-- Wrapped for re-runnability: skips if constraint already exists.
do $$
begin
    alter table kals
        add constraint kals_organizer_id_fk
        foreign key (organizer_id) references profiles(id);
exception when duplicate_object then null;
end
$$;

comment on table kals is 'KAL aggregate root. ULIDs generated app-side (26 chars text).';
comment on column kals.invite_token is 'Opaque token for join-by-link. Generated at creation, not editable in MVP.';
comment on column kals.deleted_at is 'Soft delete. RLS excludes rows with non-null deleted_at.';
comment on column kals.cover_path is 'Storage path in kal-photos bucket: {kal_id}/portada.webp';

-- =============================================================================
-- kal_locales — enabled languages for a KAL (≥1 required, enforced app-side)
-- =============================================================================
create table if not exists kal_locales (
    kal_id text not null,
    locale text not null,

    constraint kal_locales_pk primary key (kal_id, locale),
    constraint kal_locales_kal_fk foreign key (kal_id) references kals(id) on delete cascade,
    constraint kal_locales_locale_iso check (locale ~ '^[a-z]{2}$')
);

comment on table kal_locales is 'ISO 639-1 locales enabled for a KAL. Minimum 1 enforced by application invariant.';

-- =============================================================================
-- kal_files — pattern files per locale (kal-level, not clue-level)
-- =============================================================================
create table if not exists kal_files (
    upload_id      text        not null primary key,
    kal_id         text        not null,
    file_name      text        not null,
    file_path      text        not null,
    file_size      integer     not null,
    file_extension text        not null,
    locale         text        not null,
    uploaded_at    timestamptz not null default now(),

    constraint kal_files_upload_id_len check (length(upload_id) = 26),
    constraint kal_files_kal_fk foreign key (kal_id) references kals(id) on delete cascade,
    constraint kal_files_name_not_blank check (trim(file_name) <> ''),
    constraint kal_files_path_not_blank check (trim(file_path) <> ''),
    constraint kal_files_size_positive check (file_size > 0),
    constraint kal_files_extension_allowed check (file_extension in ('pdf', 'csv', 'txt')),
    constraint kal_files_locale_iso check (locale ~ '^[a-z]{2}$')
);

create index if not exists kal_files_kal_id_idx on kal_files (kal_id);

comment on table kal_files is 'Pattern file versions per locale at KAL level (storage bucket: kal-patterns).';

-- =============================================================================
-- clues — pistes (entities within Kal aggregate)
-- =============================================================================
create table if not exists clues (
    id             text        not null primary key,
    kal_id         text        not null,
    name           text        not null,
    description    text,
    starts_on      timestamptz not null,
    ends_on        timestamptz not null,
    updated_at     timestamptz not null default now(),
    locale         text        not null,

    -- Embedded file (1:1 per clue in current domain model)
    file_name      text        not null,
    file_path      text        not null,
    file_size      integer     not null,
    file_extension text        not null,
    file_locale    text        not null,
    file_upload_id text        not null,
    file_uploaded_at timestamptz not null default now(),

    constraint clues_id_length check (length(id) = 26),
    constraint clues_kal_fk foreign key (kal_id) references kals(id) on delete cascade,
    constraint clues_name_not_blank check (trim(name) <> ''),
    constraint clues_date_range check (ends_on > starts_on),
    constraint clues_locale_iso check (locale ~ '^[a-z]{2}$'),
    constraint clues_file_name_not_blank check (trim(file_name) <> ''),
    constraint clues_file_path_not_blank check (trim(file_path) <> ''),
    constraint clues_file_size_positive check (file_size > 0),
    constraint clues_file_extension_allowed check (file_extension in ('pdf', 'csv', 'txt')),
    constraint clues_file_locale_iso check (file_locale ~ '^[a-z]{2}$'),
    constraint clues_file_upload_id_len check (length(file_upload_id) = 26)
);

create index if not exists clues_kal_id_idx on clues (kal_id);

comment on table clues is 'Pistes (clues) within a KAL. Each has exactly one pattern file (embedded). Dates must fall within parent KAL range (enforced app-side).';
comment on column clues.starts_on is 'Release date — content not visible to participants before this.';
comment on column clues.locale is 'Language the clue is delivered in. Must be one of the KAL enabled locales (enforced app-side).';

-- =============================================================================
-- meetings — visual meetings (entities within Kal aggregate)
-- =============================================================================
create table if not exists meetings (
    id           text        not null primary key,
    kal_id       text        not null,
    clue_id      text,
    title        text        not null,
    url          text        not null,
    scheduled_at timestamptz not null,
    timezone     text        not null default 'Europe/Madrid',

    constraint meetings_id_length check (length(id) = 26),
    constraint meetings_kal_fk foreign key (kal_id) references kals(id) on delete cascade,
    constraint meetings_clue_fk foreign key (clue_id) references clues(id) on delete cascade,
    constraint meetings_clue_id_length check (clue_id is null or length(clue_id) = 26),
    constraint meetings_title_not_blank check (trim(title) <> ''),
    constraint meetings_url_https check (url ~ '^https://')
);

create index if not exists meetings_kal_id_idx on meetings (kal_id);
create index if not exists meetings_clue_id_idx on meetings (clue_id) where clue_id is not null;

comment on table meetings is 'Visual meetings (Zoom/Meet link + schedule). clue_id null = KAL-level meeting; set = meeting of that clue. Phase 2 adds multi-language (several meetings per scope).';
comment on column meetings.clue_id is 'Null for KAL-level meetings. When set, the meeting belongs to a clue and stays hidden until the clue is released.';
comment on column meetings.timezone is 'IANA timezone of the organizer. scheduled_at stored as UTC; timezone for display.';

-- =============================================================================
-- debate_rooms — 1 room per KAL (MVP), text+image chat via Supabase Realtime
-- =============================================================================
create table if not exists debate_rooms (
    id         text        not null primary key,
    kal_id     text        not null,
    created_at timestamptz not null default now(),

    constraint debate_rooms_id_length check (length(id) = 26),
    constraint debate_rooms_kal_fk foreign key (kal_id) references kals(id) on delete cascade
);

-- Exactly one room per KAL in MVP
create unique index if not exists debate_rooms_kal_id_unique
    on debate_rooms (kal_id);

comment on table debate_rooms is 'Debate classroom: 1 per KAL (MVP). Messages live in debate_messages (separate migration). FE writes directly via Supabase RLS.';

-- =============================================================================
-- RLS helpers (functions)
-- =============================================================================

-- Helper: is the calling user the organizer of a given KAL?
-- Requires: profiles.external_id links auth.uid() to profiles.id (internal ULID).
-- Usage in policies: is_kal_organizer(kals.id)
create or replace function is_kal_organizer(p_kal_id text)
returns boolean
language sql
stable
security definer
set search_path = ''
as $$
    select exists (
        select 1
        from public.kals k
        join public.profiles p on p.id = k.organizer_id
        where k.id = p_kal_id
          and k.deleted_at is null
          and p.external_id = (select auth.uid()::text)
    );
$$;

-- Helper: is the calling user a member of a given KAL?
-- A member is either the organizer OR has an active participation.
-- STUB: participations table does not exist yet. This function will be
-- updated when the Participation ticket lands. For now it only checks
-- organizer status, which is correct for CreateKal (organizer is sole member).
create or replace function is_kal_member(p_kal_id text)
returns boolean
language sql
stable
security definer
set search_path = ''
as $$
    select exists (
        select 1
        from public.kals k
        join public.profiles p on p.id = k.organizer_id
        where k.id = p_kal_id
          and k.deleted_at is null
          and p.external_id = (select auth.uid()::text)
    )
    -- TODO: OR exists (select 1 from public.participations ...)
    ;
$$;

-- Helper: is a clue released (past its starts_on date)?
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
          and c.starts_on <= now()
    );
$$;

-- =============================================================================
-- RLS policies
-- =============================================================================

alter table kals enable row level security;
alter table kal_locales enable row level security;
alter table kal_files enable row level security;
alter table clues enable row level security;
alter table meetings enable row level security;
alter table debate_rooms enable row level security;

-- kals: members can read non-deleted; organizer can write
drop policy if exists kals_select_member on kals;
create policy kals_select_member on kals
    for select using (
        deleted_at is null
        and is_kal_member(id)
    );

drop policy if exists kals_insert_organizer on kals;
create policy kals_insert_organizer on kals
    for insert with check (
        -- The inserting user must be the organizer (profiles.external_id = auth.uid())
        exists (
            select 1 from public.profiles p
            where p.id = organizer_id
              and p.external_id = (select auth.uid()::text)
        )
    );

drop policy if exists kals_update_organizer on kals;
create policy kals_update_organizer on kals
    for update using (is_kal_organizer(id))
    with check (is_kal_organizer(id));

-- kal_locales: same as parent kal
drop policy if exists kal_locales_select_member on kal_locales;
create policy kal_locales_select_member on kal_locales
    for select using (is_kal_member(kal_id));

drop policy if exists kal_locales_insert_organizer on kal_locales;
create policy kal_locales_insert_organizer on kal_locales
    for insert with check (is_kal_organizer(kal_id));

-- kal_files: members read; organizer writes
drop policy if exists kal_files_select_member on kal_files;
create policy kal_files_select_member on kal_files
    for select using (is_kal_member(kal_id));

drop policy if exists kal_files_insert_organizer on kal_files;
create policy kal_files_insert_organizer on kal_files
    for insert with check (is_kal_organizer(kal_id));

-- clues: members read released only; organizer full access
drop policy if exists clues_select_member on clues;
create policy clues_select_member on clues
    for select using (
        is_kal_member(kal_id)
        and is_clue_released(id)
    );

drop policy if exists clues_select_organizer on clues;
create policy clues_select_organizer on clues
    for select using (is_kal_organizer(kal_id));

drop policy if exists clues_insert_organizer on clues;
create policy clues_insert_organizer on clues
    for insert with check (is_kal_organizer(kal_id));

drop policy if exists clues_update_organizer on clues;
create policy clues_update_organizer on clues
    for update using (is_kal_organizer(kal_id))
    with check (is_kal_organizer(kal_id));

-- meetings: members read KAL-level ones plus those of released clues; organizer full access
drop policy if exists meetings_select_member on meetings;
create policy meetings_select_member on meetings
    for select using (
        is_kal_member(kal_id)
        and (clue_id is null or is_clue_released(clue_id))
    );

drop policy if exists meetings_select_organizer on meetings;
create policy meetings_select_organizer on meetings
    for select using (is_kal_organizer(kal_id));

drop policy if exists meetings_insert_organizer on meetings;
create policy meetings_insert_organizer on meetings
    for insert with check (is_kal_organizer(kal_id));

drop policy if exists meetings_update_organizer on meetings;
create policy meetings_update_organizer on meetings
    for update using (is_kal_organizer(kal_id))
    with check (is_kal_organizer(kal_id));

-- debate_rooms: members read; system/organizer creates (via backend service_role, bypasses RLS)
drop policy if exists debate_rooms_select_member on debate_rooms;
create policy debate_rooms_select_member on debate_rooms
    for select using (is_kal_member(kal_id));

-- No insert policy for debate_rooms: created by backend (service_role key, bypasses RLS)
-- in the same transaction as CreateKal.
