---
name: Managing Migrations
description: Migration lifecycle amb el CLI de Supabase — crear, aplicar en local, comprovar l'estat i desfer. Use when creating, running, or reverting database migrations. NEVER write migration files from scratch by hand.
---

ALWAYS prepend to your messages "following Managing Migrations skill..."

<managing-migrations>

<critical>
Les migracions d'aquest repo són **SQL pur gestionat pel CLI de Supabase**, no un ORM.
No hi ha Doctrine Migrations, ni Alembic, ni cap target de migracions al `Makefile`
(`grep -iE 'migrat' Makefile` no troba res — no el busquis, no hi és).

Genera SEMPRE el fitxer amb `supabase migration new <nom>` i després omple'l.
Crear el fitxer amb Write inventant-te el timestamp del nom trenca l'ordre
d'aplicació i la taula d'historial.
</critical>

<workflow>

<subsection name="1. Crear la migració">
```bash
supabase migration new <nom_en_snake_case>
```

Crea `supabase/migrations/<timestamp>_<nom>.sql` buit. El CLI viu a la màquina
(Homebrew), **no** dins de Docker: aquesta és l'excepció a «tot va per `make` /
`docker compose run`», que aplica al codi PHP.

Després omple el fitxer generat amb Edit/Write. Convencions del repo, mirant
les migracions que ja hi ha:

- Capçalera `-- Migration: <què i per què>` explicant el **per què**, no el què.
  Les migracions de fix són la millor documentació que tenim de les trampes de
  RLS ja trepitjades (veure `20260805085005_fix_kals_insert_policy.sql`)
- `create table if not exists` / `add column if not exists`
- `drop policy if exists` abans de cada `create policy` (les policies no tenen
  `create or replace`)
- `comment on table` / `comment on column` per a tot el que no sigui obvi
- Helpers de RLS: `security definer` + `set search_path = ''` i noms de taula
  qualificats (`public.kals`). Sense el `search_path` buit hi ha hagut un bug
  real (`20260805200929_fix_generate_ulid_search_path.sql`)
- ULIDs generats a l'aplicació, mai a la BD (excepció: `profiles.id`, que el
  crea el trigger `handle_new_user()`)
</subsection>

<subsection name="2. Aplicar-la en local">
```bash
supabase migration up --local
```

Aplica només les pendents i **no esborra dades**. És el camí per defecte.

`supabase db reset` també funciona, però **replica tot l'esquema des de zero i
esborra les dades locals** (perfils, KALs de prova, i el token bearer que
tinguessis minat). No el facis servir sense dir-ho abans a la desenvolupadora.

`supabase db push` és per a la BD **remota**; avui el projecte no està linkat
(`supabase link`) i no hi ha entorn remot.
</subsection>

<subsection name="3. Comprovar l'estat">
```bash
supabase status                       # el stack local aixecat?
docker exec supabase_db_kal-app psql -U postgres -d postgres \
  -c "select version, name from supabase_migrations.schema_migrations order by version desc limit 10;"
```

`supabase migration list` demana projecte linkat i avui peta amb
`LegacyProjectNotLinkedError`: fes servir la consulta de dalt.

Si `supabase status` diu que el contenidor de la BD està aturat, `supabase start`.
</subsection>

<subsection name="4. Deriva de l'historial (ja ha passat)">
Si `migration up` falla amb `Remote migration versions not found in local
migrations directory`, vol dir que la BD local té aplicada una migració que
**no és al Git**. Passos:

1. Mira quina és amb la consulta de `schema_migrations` de dalt
2. **Digues-ho a la desenvolupadora abans de tocar res**: un fitxer que és a la
   seva BD i no al repo pot ser feina seva sense commitejar, i s'ha de recuperar
   o descartar conscientment
3. Per desencallar sense perdre dades:
   `supabase migration repair --local --status reverted <version>`
   (només toca la taula d'historial; els efectes del SQL segueixen a la BD)

Va passar el 2026-08-11 amb `20260805205226_backfill_debate_rooms`.
</subsection>

<subsection name="5. Rollback">
No hi ha `down` migrations. Per desfer: una migració nova que reverteixi
(`drop column`, `drop policy` + recrear l'anterior…), o `supabase db reset` si
el que vols és tornar a l'esquema del Git i acceptes perdre les dades locals.
</subsection>

</workflow>

<after-any-migration>
L'esquema i el codi han d'anar junts o es podreixen sense que res ho digui:

- `make test-db` — els tests d'integració van contra l'esquema REAL de
  `supabase/migrations/`. Cap taula es crea des dels tests, a propòsit
- `make qa` — el gate hermètic (no toca BD)
- Si has tocat policies, mira si cal un test a `tests/Integration/Rls/`
- **CI no corre `make test-db`** (no hi ha Supabase al runner): córrer-lo en
  local abans de fer merge no és opcional
</after-any-migration>

<rules>
- NEVER crear el fitxer de migració a mà amb un timestamp inventat: `supabase migration new`
- NEVER `supabase db reset` sense avisar (esborra les dades locals)
- NEVER `supabase db push` (no hi ha remot linkat)
- Editar el fitxer generat sí; això és el flux normal
- Migració endavant-compatible: el codi antic ha de seguir funcionant amb
  l'esquema nou (afegir columnes nullable, no reaprofitar noms)
</rules>

<checklist>
- [ ] Fitxer generat amb `supabase migration new`
- [ ] Capçalera que explica el PER QUÈ
- [ ] `if not exists` / `drop policy if exists` on toca
- [ ] Aplicada amb `supabase migration up --local`
- [ ] `make test-db` i `make qa` verds
</checklist>

</managing-migrations>
