# Spec: el xat de l'aula

> Status: **Draft**
> Escrit 2026-08-16. Primera de dues specs de l'aula: aquesta cobreix el **xat**
> (esquema + RLS + Realtime), que va directe a Supabase. La lectura de l'aula
> (llistat d'aules, KAL amb galeria i pistes alliberades) són endpoints de
> Symfony i aniran a `aula-lectura.md`.

---

## Problem

`debate_rooms` es crea amb cada KAL des del primer dia (a la mateixa transacció
que l'arrel), però **no hi ha on escriure**: `debate_messages` es va deixar
anotada com a «separate migration» al comentari de la taula i mai es va fer. Una
aula buida i sense taula de missatges és, ara mateix, una fila.

La tesi de la desenvolupadora és que **sense conversa un KAL no viu**: la gent
s'apunta, teixeix i ensenya la feina, i això passa parlant. Avui aquesta conversa
passa a Telegram o Discord, fora de l'eina, que és exactament el que l'eina vol
substituir.

## Goals / Non-Goals

**Goals**

- Una aula per KAL on les membres escriuen text i hi poden adjuntar una foto.
- Els missatges arriben **en directe** a qui té l'aula oberta (Supabase Realtime).
- L'organitzadora pot **amagar** un missatge (moderació).
- Escriptura i lectura protegides per **RLS**, no per la UI: el client insereix
  directament contra Supabase, sense passar per Symfony.
- L'esquema es dissenya **sabent que vindran fils i canals**, de manera que
  afegir-los no obligui a reescriure ni la taula ni les polítiques.

**Non-Goals**

- **Fils i canals ara.** Una sola aula per KAL i missatges plans, com diu l'MVP.
  El que es fa avui és no tancar-se la porta (veure Esquema).
- Reaccions, edició de missatges d'altri, cerca, comptadors de no llegits,
  notificacions push, mencions.
- Endpoints de Symfony per al xat. Si en calgués un, seria senyal que alguna
  cosa d'aquesta spec no encaixa.
- La resta de l'aula (llistat, galeria, pistes alliberades): `aula-lectura.md`.

## Esquema

Dues taules: `debate_rooms`, que ja existeix i canvia, i `debate_messages`, que
és nova. La segona és la que faltava des del primer dia — el comentari de
`debate_rooms` la deixava anotada com a «separate migration».

### Una aula per idioma habilitat

Decidit el 2026-08-24, i és un canvi respecte del que diu CLAUDE.md avui («MVP:
1 sola aula; Fase 2: múltiples per idioma»): l'aula és **per idioma**, no per
KAL. Un KAL amb `ca` i `es` habilitats té dues aules des del minut u.

```sql
-- 1. La columna, directament not null: la taula es dona per buida (veure sota).
alter table debate_rooms add column locale text not null;

-- 2. El locale de l'aula ha de ser un dels habilitats al KAL. Aquí sí que ho pot
--    fer la BD, perquè kal_locales té PK (kal_id, locale).
alter table debate_rooms
    add constraint debate_rooms_kal_locale_fk
    foreign key (kal_id, locale) references kal_locales (kal_id, locale)
    on delete restrict;

-- 3. Una aula per idioma, ja no una per KAL.
drop index debate_rooms_kal_id_unique;
create unique index debate_rooms_kal_locale_unique
    on debate_rooms (kal_id, locale) where deleted_at is null;

-- 4. Necessari per la FK composta de debate_messages (veure sota).
create unique index debate_rooms_id_kal_unique on debate_rooms (id, kal_id);
```

**La migració dona `debate_rooms` per buida.** No hi ha backfill ni `set not null`
en dos passos perquè avui no hi ha dades reals enlloc: ni desplegament ni entorn
remot. És un supòsit que envelleix malament — si un entorn local té files de
proves, la resposta és `supabase db reset`, no escriure un backfill. Qui faci el
primer desplegament ha de saber que això es va decidir a consciència i que a
partir d'aquell moment ja no serà cert.

**Què cobreix aquesta FK.** La direcció «no pots crear una aula en un idioma que
el KAL no té habilitat», que és real i barata. L'`on delete restrict` hi és per
si algun dia algú fa un hard delete a mà: que peti, en comptes d'endur-se
conversa per davant.

**Editar els idiomes d'un KAL queda fora d'aquesta spec** — què passa quan
s'afegeix un idioma, i sobretot què passa amb una aula que ja té conversa quan se
li treu l'idioma, es tracta a [`kal-locales.md`](kal-locales.md).

### `debate_messages`

```sql
create table debate_messages (
    id         text        not null primary key,
    room_id    text        not null,
    kal_id     text        not null,   -- denormalitzat, veure sota
    author_id  text        not null,
    body       text,
    image_path text,
    hidden     boolean     not null default false,
    created_at timestamptz not null default now(),
    edited_at  timestamptz,
    deleted_at timestamptz,

    constraint debate_messages_id_length     check (length(id) = 26),
    constraint debate_messages_author_length check (length(author_id) = 26),
    constraint debate_messages_body_length   check (body is null or length(body) <= 2000),
    constraint debate_messages_not_empty     check (
        trim(coalesce(body, '')) <> '' or image_path is not null
    ),
    constraint debate_messages_room_fk foreign key (room_id, kal_id)
        references debate_rooms (id, kal_id) on delete cascade,
    constraint debate_messages_author_fk foreign key (author_id)
        references profiles (id)
);

create index debate_messages_room_created
    on debate_messages (room_id, created_at desc) where deleted_at is null;

alter table debate_messages enable row level security;
alter publication supabase_realtime add table debate_messages;
```

Les quatre decisions d'aquesta taula, amb el perquè:

- **`room_id`, no `kal_id` sol.** Amb aula per idioma, `kal_id` sol seria ambigu:
  no diria a quina aula va el missatge. Era la decisió que sí que ens podia
  tancar la porta a canals i fils, i queda resolta.
- **`kal_id` duplicat, amb FK composta.** Sense ell, cada policy hauria de fer
  `is_kal_member((select kal_id from debate_rooms where id = room_id))` — un
  subselect per fila, i Realtime avalua RLS per cada missatge que entrega. Amb la
  columna, la policy és `is_kal_member(kal_id)` i prou. La denormalització no pot
  divergir: la FK apunta a `debate_rooms (id, kal_id)`, o sigui que no es pot
  inserir un missatge que declari un `kal_id` que no sigui el de la seva aula.
  És el que justifica l'índex `debate_rooms_id_kal_unique` del bloc anterior.
- **Sense `reply_to_id`.** Els Goals demanen no tancar-se la porta als fils, i no
  se'ns tanca: afegir-los serà un `alter table add column`, sense migració de
  dades ni tocar cap policy existent. Posar-la avui és una columna morta que
  surt a tots els `select *` i que probablement no serà la forma bona — els fils
  solen voler arrel i comptador, no un self-FK pelat.
- **`image_path` a la mateixa fila, no taula d'adjunts.** L'MVP diu una foto per
  missatge. Path segons la convenció ja escrita a CLAUDE.md:
  `{kal_id}/debat/{message_id}.webp`, al bucket `kal-photos`.

### Conseqüències sobre codi que ja existeix

Passar d'una aula per KAL a una per idioma no és només SQL:

- `Kal::$debateRoom` passa de ser una `DebateRoom` a una col·lecció, i
  `Kal::create()` n'ha de crear una per cada locale de `Locales`.
- `DebateRoom` guanya el seu `Locale`, i el docblock de la classe deixa de dir
  «una sola per KAL a l'MVP».
- `KalHydrator` (avui llença `missingDebateRoom()` amb zero files) i
  `KalRepository::create()` (avui un sol `insert`) han de treballar amb la
  llista.
- `KalRepository::CHILD_TABLES` — les aules ja hi són; hi entra `debate_messages`
  (veure «Esborrar i editar el propi missatge»).
- CLAUDE.md: «MVP: 1 sola aula» deixa de ser cert, i «múltiples aules de xat per
  idioma» surt de la llista de Fora de l'MVP.

### Esborrar i editar el propi missatge

Decidit el 2026-08-24, les tres coses alhora:

- **Els missatges entren a la cascada del soft delete del KAL.** N'hi ha prou
  amb afegir `debate_messages` a `KalRepository::CHILD_TABLES`: el bucle del
  `delete()` fa `UPDATE <taula> SET deleted_at … WHERE kal_id = :kal_id`, i com
  que el `kal_id` és a la fila (la denormalització del bloc anterior), no cal ni
  join ni cas especial. És l'única taula de l'arbre que el backend escriu sense
  ser-ne mai el lector.
- **Una autora pot esborrar el seu missatge**, sempre amb `deleted_at` i mai amb
  `DELETE`. No és el mateix que el `hidden` de l'organitzadora: `hidden` és
  moderació i l'organitzadora el continua veient; `deleted_at` treu la fila de la
  conversa per a tothom.
- **`edited_at` hi va.** Editar el missatge propi entra a l'MVP — els Non-Goals
  només descarten editar el d'altri. El posa un trigger quan canvia el `body`,
  no el client (veure RLS).

## RLS

Aquí és on viu de veritat la seguretat del xat: el frontend insereix i llegeix
`debate_messages` directament amb `supabase-js`, i el backend només hi entra per
la cascada del delete — amb service_role, o sigui **saltant-se RLS**. No hi ha
cap capa d'aplicació que tapi un forat de policy.

### Helpers: cap de nou

- `is_kal_member(kal_id)` — participació activa o ser l'organitzadora
  (`20260804112500_participations.sql`).
- `is_kal_organizer(kal_id)` — `20260725134400_kal_mvp_schema.sql`.
- `is_own_profile(profile_id)` — `20260805085005_fix_kals_insert_policy.sql`,
  tradueix `auth.uid()` a l'id intern de `profiles`.

Els tres reben el `kal_id` o l'`author_id` que la fila ja porta. És exactament
per això que `kal_id` és a `debate_messages`: sense la columna, cada avaluació de
policy hauria d'anar a buscar-lo a `debate_rooms`.

### Grants

```sql
grant select, insert on debate_messages to authenticated;
grant update (body, hidden, deleted_at) on debate_messages to authenticated;
```

Sense `delete`: aquí no s'esborra res de veritat. L'`update` és **per columnes** a
propòsit — `id`, `room_id`, `kal_id`, `author_id`, `created_at` i `edited_at` no
els ha de poder escriure ningú des del client, i això una policy no ho pot dir:
les policies filtren files, no columnes. `edited_at` queda fora perquè el posa el
trigger, no qui escriu.

### Policies

```sql
-- Lectura: membres del KAL. Els amagats, només l'organitzadora.
create policy debate_messages_select_member on debate_messages
    for select using (
        deleted_at is null
        and is_kal_member(kal_id)
        and (not hidden or is_kal_organizer(kal_id))
    );

-- Escriptura: membre del KAL, signant amb el seu propi perfil, a una aula viva.
create policy debate_messages_insert_member on debate_messages
    for insert with check (
        is_kal_member(kal_id)
        and is_own_profile(author_id)
        and not hidden
        and deleted_at is null
        and exists (
            select 1 from debate_rooms r
             where r.id = room_id and r.deleted_at is null
        )
    );

-- L'autora edita o esborra el seu.
create policy debate_messages_update_author on debate_messages
    for update using (is_own_profile(author_id) and deleted_at is null)
            with check (is_own_profile(author_id));

-- L'organitzadora modera.
create policy debate_messages_update_organizer on debate_messages
    for update using (is_kal_organizer(kal_id) and deleted_at is null)
            with check (is_kal_organizer(kal_id));
```

Tres coses que no són òbvies:

- **`is_own_profile(author_id)` a l'insert no és decoració.** Sense aquesta línia,
  una membre pot inserir un missatge amb l'`author_id` d'una altra: a RLS li és
  igual cap a on apunti la columna si no li ho preguntes.
- **La FK composta també fa feina de seguretat**, no només d'integritat: garanteix
  que el `kal_id` que la policy comprova és el de l'aula on va el missatge. Sense
  ella, `is_kal_member(kal_id)` seria comprovar un camp que qui escriu tria.
- **L'`exists` sobre `debate_rooms`** hi és perquè la FK **no** mira `deleted_at`.
  Sense ell, una membre podria seguir escrivint a l'aula d'un idioma que ja s'ha
  tret del KAL — mecanisme que es defineix a [`kal-locales.md`](kal-locales.md),
  encara per implementar. **És l'únic cas que cobreix**, i val la pena dir-ho
  perquè el candidat evident no ho és: amb el KAL soft-deleted no hi afegeix
  res, perquè `is_kal_member(kal_id)` ja exigeix `kals.deleted_at is null` a les
  seves dues branques (`20260804112500_participations.sql`) i la policy talla a
  la primera condició. Es paga un cop per fila inserida, no per fila llegida.

### El que les policies no poden dir: qui toca quina columna

Les dues policies d'`update` són permissives i s'acumulen amb un OR, i el grant de
columnes és per rol — totes les membres són `authenticated`. Tal com queda, doncs,
l'organitzadora podria reescriure el `body` d'una altra (que és un Non-Goal
declarat) i una autora podria posar-se `hidden` ella sola. RLS no ho sap
expressar; la resposta estàndard de Postgres és un trigger:

```sql
create or replace function debate_messages_guard_update()
returns trigger
language plpgsql
security definer
set search_path = ''
as $$
begin
    -- El backend (service_role, cascada del delete del KAL) no té auth.uid().
    -- Nota: service_role salta RLS, però NO salta triggers.
    if (select auth.uid()) is null then
        return new;
    end if;

    if new.id <> old.id
       or new.room_id <> old.room_id
       or new.kal_id <> old.kal_id
       or new.author_id <> old.author_id
       or new.created_at <> old.created_at then
        raise exception 'debate_messages: immutable column' using errcode = '42501';
    end if;

    if public.is_own_profile(old.author_id) then
        -- L'autora: el seu text i el seu esborrat, mai el flag de moderació.
        if new.hidden is distinct from old.hidden then
            raise exception 'debate_messages: only the organizer hides'
                using errcode = '42501';
        end if;
        if new.body is distinct from old.body then
            new.edited_at := now();
        end if;
    elsif public.is_kal_organizer(old.kal_id) then
        -- L'organitzadora: només el flag.
        if new.body is distinct from old.body
           or new.image_path is distinct from old.image_path
           or new.deleted_at is distinct from old.deleted_at then
            raise exception 'debate_messages: the organizer can only hide'
                using errcode = '42501';
        end if;
    end if;

    return new;
end;
$$;

create trigger debate_messages_guard_update
    before update on debate_messages
    for each row execute function debate_messages_guard_update();
```

El trigger també és qui posa `edited_at`, que per això no està al grant: si ho
deixéssim al client, un missatge editat podria arribar sense marca d'edició
simplement no escrivint la columna.

### `debate_rooms`: sense canvis

`debate_rooms_select_member` ja diu `deleted_at is null and is_kal_member(kal_id)`
i segueix sent correcta amb una aula per idioma — una membre veu totes les aules
del seu KAL i tria en quina escriu. Els idiomes que una participant «parla» no
són dada del sistema, i filtrar-li aules per idioma seria decidir per ella.

### Conseqüència que arrossega Realtime

Amb RLS, un `UPDATE` que fa una fila invisible per a algú **no li genera cap
event**: Realtime avalua la policy de `select` sobre la fila nova, i si ja no hi
passa, no l'entrega. O sigui que amagar un missatge (o esborrar-lo l'autora) no
desapareix sol de la pantalla de qui el tenia obert. Es tracta a «Realtime i
moderació», no aquí, però la causa és aquesta secció.

## Realtime i moderació

**Realtime entrega missatges nous, i prou.** El client se subscriu als `insert`
de `debate_messages`; RLS s'avalua per cada fila entregada, o sigui que una
membre només rep els missatges dels KALs on és membre, sense cap filtre de
confiança al client. Els missatges amagats no hi arriben mai per aquesta via:
la policy d'`insert` exigeix `not hidden`, així que no existeix un missatge que
neixi amagat.

**Amagar i esborrar NO són en temps real.** Quan l'organitzadora amaga un
missatge, o l'autora esborra el seu, la fila no desapareix de la pantalla de qui
tenia l'aula oberta: hi segueix fins que recarrega. La moderació fa efecte a la
següent càrrega de l'aula, no al segon següent.

Això és una simplificació **acceptada a consciència**, no un descuit, i la causa
és la que ja s'explica a «Conseqüència que arrossega Realtime»: amb RLS, un
`UPDATE` que fa una fila invisible per a algú no li genera cap event, perquè
Realtime avalua la policy de `select` sobre la fila **nova** i, si ja no hi
passa, no l'entrega. Fer que la retirada fos immediata voldria dir sortir d'RLS
— un canal a part, un event sintètic, o entregar a tothom un identificador de
missatge retirat i confiar que el client l'esborri de la pantalla. Tot això és
mecanisme nou per a un cas que a l'MVP passa poc i que la següent càrrega ja
resol. Que un missatge lleig es vegi uns minuts més a les pestanyes obertes és
un cost menor que el d'inventar-se un segon camí de dades.

**Conseqüència que estalvia feina:** com que només calen events d'`insert`, la
taula **no** necessita `replica identity full`. Això només caldria per rebre
`update`/`delete` amb RLS, on Postgres ha de publicar la fila antiga sencera
perquè la policy es pugui avaluar. La publicació es queda com diu l'esquema:
`alter publication supabase_realtime add table debate_messages`, i res més.

**Risc, verificat: avui no hi ha Realtime a cap taula del projecte.** No hi ha
cap `alter publication` a `supabase/migrations/`, i `supabase/config.toml` no té
secció `[realtime]`. `debate_messages` seria la primera taula publicada. O sigui
que la línia de la publicació és el que *hauria* de bastar segons la
documentació, però que els `insert` arribin de veritat al client — amb RLS
activa i amb el JWT de la membre — és una **verificació pendent** contra la
Supabase local, no un fet comprovat. Si falla, el problema serà de configuració
del servei, no de l'esquema d'aquesta spec.

## Scenarios

_(pendent)_

## Acceptance criteria

_(pendent)_

## Constraints

_(pendent)_

## Out of scope

_(pendent)_

## Trade-offs

_(pendent)_

## Risks & assumptions

_(pendent)_

## Open questions

_Aquesta secció es va deixar oberta amb un sol punt, afegit el 2026-08-29 quan
una decisió de fora d'aquesta spec la va contradir. La resta de seccions
`_(pendent)_` segueixen pendents._

1. **La moderació és una escriptura d'organitzadora fora del backend, i la regla
   del 2026-08-29 diu que això ja no existeix.** Aquell dia es va decidir que
   **tota escriptura de gestió passa pel backend**: la migració
   `20260829210457_lock_kal_writes_to_backend.sql` revoca els grants
   d'escriptura de `authenticated` sobre `kals`, `kal_locales`, `kal_files`,
   `clues`, `meetings` i `debate_rooms`, i dropa les vuit policies
   `*_insert_organizer`/`*_update_organizer`. Aquesta spec està construïda sobre
   el contrari: el client escriu `debate_messages` directament amb `supabase-js`.
   El punt de fricció **no** són els missatges de les membres —escriure al xat és
   participació, no gestió, i és la superfície de la participant— sinó l'`UPDATE`
   de `hidden` amb què **l'organitzadora amaga un missatge**: és una acció de
   gestió, i aquí la fa el client contra Supabase, amb el
   `grant update (body, hidden, deleted_at)` i la policy
   `debate_messages_update_organizer`. Les dues sortides visibles: acceptar-ho
   com a excepció declarada i escrita, o treure la moderació a un endpoint de
   Symfony i deixar el client només amb `body` i `deleted_at` de l'autora (que
   canviaria el grant, la policy i el trigger d'aquesta spec). **No decidit**:
   la spec està aparcada i la decisió és de l'arquitecta.
