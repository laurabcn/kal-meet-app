-- Local/dev: create profiles on Auth signup (missing from profiles_stub).
-- Also backfills auth.users that signed up before this trigger existed.

create extension if not exists pgcrypto;

create or replace function public.generate_ulid()
returns text
language plpgsql
volatile
as $$
declare
  encoding   constant text := '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
  timestamp  bigint;
  output     text := '';
  unix_ms    bigint;
  i          int;
  rand_bytes bytea;
  value      numeric := 0;
begin
  unix_ms := (extract(epoch from clock_timestamp()) * 1000)::bigint;
  timestamp := unix_ms;

  for i in 0..9 loop
    output := substr(encoding, (timestamp % 32)::int + 1, 1) || output;
    timestamp := timestamp >> 5;
  end loop;

  rand_bytes := gen_random_bytes(10);
  for i in 0..9 loop
    value := value * 256 + get_byte(rand_bytes, i);
  end loop;

  for i in 0..15 loop
    output := output || substr(encoding, mod(value, 32)::int + 1, 1);
    value := floor(value / 32);
  end loop;

  -- random digits were appended LSB-first; reverse the last 16 chars
  return substr(output, 1, 10) || reverse(substr(output, 11, 16));
end;
$$;

create or replace function public.handle_new_user()
returns trigger
language plpgsql
security definer
set search_path = public
as $$
begin
  insert into public.profiles (id, external_id)
  values (public.generate_ulid(), new.id::text)
  on conflict do nothing;

  return new;
end;
$$;

drop trigger if exists on_auth_user_created on auth.users;

create trigger on_auth_user_created
  after insert on auth.users
  for each row
  execute function public.handle_new_user();

insert into public.profiles (id, external_id)
select public.generate_ulid(), u.id::text
from auth.users u
where not exists (
  select 1 from public.profiles p where p.external_id = u.id::text
);
