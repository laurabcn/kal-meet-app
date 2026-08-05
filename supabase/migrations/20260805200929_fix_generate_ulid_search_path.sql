-- Migration: el registre d'usuàries estava trencat
--
-- `POST /auth/v1/signup` responia 500 «Database error saving new user». No era
-- de l'aplicació: petava el trigger `handle_new_user()`, i el rollback
-- s'emportava l'INSERT a `auth.users`. Cap usuària nova es podia registrar.
--
-- La cadena:
--   1. `handle_new_user()` té `set search_path = public`.
--   2. Crida `public.generate_ulid()`, que NO tenia search_path propi i feia
--      servir `gen_random_bytes(10)` sense qualificar.
--   3. `pgcrypto` no viu a `public`: a Supabase és a l'esquema `extensions`.
--   4. Amb el search_path fixat a `public` i prou, `gen_random_bytes` no es
--      troba i la funció peta.
--
-- El que ho amagava: cridant `generate_ulid()` des d'una sessió normal FUNCIONA,
-- perquè el search_path del rol sí que porta `extensions`. Només fallava des de
-- dins del trigger, que és l'únic lloc on s'executa de veritat.
--
-- El `create extension if not exists pgcrypto` de 20260803224500 no ho salvava:
-- l'extensió ja existia a `extensions`, o sigui que era un no-op i mai la va
-- posar a `public`.
--
-- Es corregeix a la FUNCIÓ i no al trigger, perquè així `generate_ulid()` deixa
-- de dependre del search_path de qui la crida — que és el que la feia fràgil.

alter function public.generate_ulid() set search_path = public, extensions;

comment on function public.generate_ulid() is 'ULID generat en SQL. Només per a profiles.id, que crea el trigger handle_new_user() sense accés a l''aplicació; la resta d''ULIDs es generen amb symfony/uid. El search_path inclou `extensions` perquè hi viu pgcrypto (gen_random_bytes).';
