-- Migration: participations + real is_kal_member
-- Enables join-by-invite (POST /kal/participation). Updates the stub helper that
-- only recognized organizers so RLS policies that call is_kal_member see members too.

-- =============================================================================
-- participations
-- =============================================================================
create table if not exists participations (
    id         text        not null primary key,
    kal_id     text        not null,
    user_id    text        not null,
    joined_at  timestamptz not null default now(),

    constraint participations_id_length check (length(id) = 26),
    constraint participations_kal_id_len check (length(kal_id) = 26),
    constraint participations_user_id_len check (length(user_id) = 26),
    constraint participations_kal_fk foreign key (kal_id) references kals(id) on delete cascade,
    constraint participations_user_fk foreign key (user_id) references profiles(id)
);

create unique index if not exists participations_kal_user_unique
    on participations (kal_id, user_id);

create index if not exists participations_user_id_idx on participations (user_id);

comment on table participations is 'Active membership of a profile in a KAL (join via invite token). Expulsion/status is a later ticket.';
comment on column participations.user_id is 'Internal profiles.id (ULID), never auth.uid() directly.';

alter table participations enable row level security;

-- Members can read every participation row of a KAL they belong to: les
-- participants es veuen entre elles (la identitat que mostren la decideix
-- l'spec del xat, no aquesta taula).
drop policy if exists participations_select_member on participations;
create policy participations_select_member on participations
    for select using (is_kal_member(kal_id));

-- Inserts go through the backend (service_role bypasses RLS). No client insert policy.

-- =============================================================================
-- is_kal_member — organizer OR active participation
-- =============================================================================
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
    or exists (
        select 1
        from public.participations part
        join public.profiles p on p.id = part.user_id
        join public.kals k on k.id = part.kal_id
        where part.kal_id = p_kal_id
          and k.deleted_at is null
          and p.external_id = (select auth.uid()::text)
    );
$$;
