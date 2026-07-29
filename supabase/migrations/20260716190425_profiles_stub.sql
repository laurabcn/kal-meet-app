-- Stub migration: minimal profiles table for local `supabase db reset`.
--
-- In the real Supabase project this table (plus generate_ulid(), the
-- handle_new_user() trigger, and the external_id column) already exists
-- from the Users ticket (20260716190425_users_external_id_ulid.sql).
-- This stub only creates the columns that downstream migrations reference
-- (id PK, external_id) so `supabase db reset` can apply the full chain
-- locally without errors.
--
-- The stub is idempotent: `CREATE TABLE IF NOT EXISTS` + constraints
-- wrapped in DO blocks so re-runs are safe.

create table if not exists profiles (
    id          text not null primary key,
    external_id text not null,

    constraint profiles_id_length check (length(id) = 26)
);

-- external_id must be unique (one auth user → one profile)
do $$
begin
    if not exists (
        select 1 from pg_indexes
        where tablename = 'profiles' and indexname = 'profiles_external_id_unique'
    ) then
        create unique index profiles_external_id_unique on profiles (external_id);
    end if;
end
$$;

comment on table profiles is 'Stub for local dev. Full profiles table comes from the Users migration in the real Supabase project.';
