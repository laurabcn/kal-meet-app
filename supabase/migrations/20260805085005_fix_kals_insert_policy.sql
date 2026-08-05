-- Migration: `kals_insert_organizer` no podia funcionar mai
--
-- La política feia la comprovació inline:
--
--     with check (exists (select 1 from public.profiles p where ...))
--
-- i el cos d'una política s'executa amb el rol de QUI CRIDA, no com a definer.
-- `authenticated` no té SELECT sobre `profiles`, o sigui que qualsevol INSERT
-- acabava amb `permission denied for table profiles` i cap client podia crear
-- un KAL. Les altres polítiques no en pateixen perquè deleguen a
-- `is_kal_organizer` / `is_kal_member`, que són `security definer`: aquesta era
-- l'única que consultava `profiles` directament.
--
-- La correcció NO és `grant select on profiles to authenticated`: `profiles`
-- no té RLS activada, o sigui que obrir-la exposaria tots els perfils a
-- qualsevol usuària autenticada. En comptes d'això la lectura queda tancada
-- dins d'un helper `security definer`, com la resta de l'esquema.

create or replace function is_own_profile(p_profile_id text)
returns boolean
language sql
stable
security definer
set search_path = ''
as $$
    select exists (
        select 1
        from public.profiles p
        where p.id = p_profile_id
          and p.external_id = (select auth.uid()::text)
    );
$$;

comment on function is_own_profile(text) is 'Is this profiles.id the calling user? security definer so policies can check it without granting SELECT on profiles.';

drop policy if exists kals_insert_organizer on kals;
create policy kals_insert_organizer on kals
    for insert with check (is_own_profile(organizer_id));
