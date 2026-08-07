# Spec: Kal aggregate — completar l’MVP de domini (+ primera escriptura)

> Status: **RESOLT** (2026-08-04). Fitxer tancat, es conserva com a històric de
> les decisions. Els noms de classe i les rutes que cita són els d’aquell
> moment i **no** es mantenen al dia — no els facis servir com a referència del
> codi actual.
>
> **Update 2026-08-07:** contràriament al que aquest spec va decidir aleshores
> («CreateKal sense `debate_room` + backfill després»), l’aula és part de
> l’agregat i s’escriu a la mateixa transacció. No hi ha migració de backfill
> (no hi havia KALs en prod). Els missatges del xat segueixen fora.

## Problem

Ja existeix la capa `Domain` de l’agregat `Kal` (`App\Kal\Domain`: `Kal`,
`Clue`/`Clues`, `File`/`Files`, `Locales`, excepcions i tests). Això cobreix
creació en memòria amb invariants de dates, locales i fitxers per idioma.

Encara no hi ha camí d’escriptura persistit (repositori, command, migració,
HTTP) ni els camps/comportaments mínims perquè una organitzadora pugui
**crear un KAL usable** (convidar, programar una trobada). Sense tancar
aquest tall, la resta de l’MVP no té arrel estable. L’aula de debate
queda diferida fins que el xat entri a l’MVP (veure
[`kal-debate.md`](kal-debate.md)).

Aquest spec defineix què cal per donar per **completat** l’agregat Kal a
l’MVP família (domini restant + primera vertical d’escriptura). El debate
detallat viu a [`kal-debate.md`](kal-debate.md).

## Goals

- Tancar el model de domini Kal necessari per crear un KAL i convidar
  participants (camps i invariants que falten respecte a `CLAUDE.md`).
- Definir la primera operació d’escriptura: **CreateKal** (Application +
  port de repositori + adaptador DBAL + migració mínima). La fila
  `debate_room` **no** es crea aquí: el xat és candidat MVP pendent de
  validació; quan arribi, CreateKal (o una migració de backfill) crearà
  l’aula pels KALs existents.
- Deixar explícit què queda fora (PatternInfo ric, mutacions, soft delete
  API, Participation com a flux propi, aula de xat) perquè el team-lead
  pugui partir tasques sense reobrir abast.

## Non-Goals

- Implementar el xat (missatges): veure [`kal-debate.md`](kal-debate.md).
- PatternInfo complet (fitxa progressiva, versions multi-idioma del PDF de
  patró més enllà dels `File` ja modelats al Domain).
- Participation / join per invitació (spec/tasca pròpia; aquest tall només
  deixa el KAL amb `inviteToken` generat).
- Photos / galeria / ClueProgress.
- Mutacions (rename, canvi de dates, afegir pista via API) i soft delete
  exposat per HTTP.
- Controllers OpenAPI complets més enllà del mínim per CreateKal (si es
  fa UI primer via altres clients, el contracte HTTP pot ser tasca germana).

## Behavior

### Domini (completar respecte a l’estat actual)

- Un `Kal` creat té com a mínim: `id`, `organizerId`, `name`,
  `description?`, `startsOn`, `endsOn?`, `coverPath?`, `locales`,
  `files`, `clues`, `createdAt`, `updatedAt` (ja implementat).
- S’afegeix **`inviteToken`**: valor opac no buit, generat a la creació
  (no editable en aquest tall). Serveix perquè una tasca posterior
  resolgui “join per enllaç”.
- S’afegeix suport de **`Meeting`** com a col·lecció dins l’agregat (MVP:
  zero o més trobades a nivell KAL, sense `clueId`):
  - Camps: `id`, `scheduledAt`, `url` (https), `title`, `timezone`
    (default `Europe/Madrid`).
  - Es poden crear amb el Kal o afegir-se després via mètode d’agregat
    `addMeeting` (domini); l’API d’afegir meeting pot ser tasca
    immediata o just després de CreateKal.
- La creació del Kal **no** crea `debate_room`. Quan el xat entri a
  l’MVP, caldrà insert al CreateKal **i** backfill dels KALs creats
  sense aula. Detall a [`kal-debate.md`](kal-debate.md).

### Application / Infrastructure

- Command `CreateKal` (o equivalent) amb dades necessàries per
  `Kal::create` + generació d’`inviteToken`.
- Port `KalRepositoryInterface` amb `save(Kal): void` (o `add` que
  persisteix i no assumeix IDs de BD).
- Adaptador DBAL: SQL directe, transacció explícita
  (`kals` + fills mínims; sense `debate_rooms` en aquest tall).
- ULIDs generats a l’aplicació/domini, mai a Postgres (excepció
  `profiles` ja documentada).

### Fora d’aquest behavior (prerequisit del debate)

- Les RLS `is_kal_member` / `is_kal_organizer` i el flux d’unirse amb
  token queden en tasques de Participation; el CreateKal només deixa el
  token. L’aula es crea quan el xat es cablegi.

## Inputs

| Input | Source | Format | Required? |
|-------|--------|--------|-----------|
| organizerId | Auth / perfil intern | ULID | yes |
| name | Organitzadora | non-empty string | yes |
| startsOn | Organitzadora | datetime | yes |
| locales | Organitzadora | ≥1 Locale | yes |
| files | Organitzadora | Files (pot buit segons invariants actuals) | yes (colecció; pot ser buida si el domini ho permet) |
| clues | Organitzadora | Clues (pot buit) | yes (colecció; pot ser buida) |
| description | Organitzadora | string | no |
| endsOn | Organitzadora | datetime > startsOn | no |
| coverPath | Organitzadora / upload previ | storage path | no |
| meetings (opcional a create) | Organitzadora | list Meeting data | no |

## Outputs

| Output | Consumer | Format |
|--------|----------|--------|
| Kal persistit | FE / queries posteriors | id + camps + inviteToken |
| Domain errors | API / FE | codis (`kal_*`) |

## Scenarios

### Happy path

- GIVEN una organitzadora autenticada  
  WHEN envia CreateKal amb name, startsOn i ≥1 locale  
  THEN es persisteix el Kal, es genera `inviteToken`, i es retorna
  l’id del Kal (sense fila a `debate_rooms`).

- GIVEN CreateKal amb una Meeting (url https + scheduledAt)  
  WHEN es desa  
  THEN el Kal queda amb aquesta meeting.

### Edge / Error paths

- GIVEN `endsOn` ≤ `startsOn`  
  WHEN CreateKal  
  THEN `kal_invalid_date_range`, cap fila a BD.

- GIVEN file/clue amb locale no habilitat  
  WHEN CreateKal  
  THEN error de domini existent (`kal_*` de locale), cap persistència
  parcial.

- GIVEN fallo a mig insert (p.ex. `kal_locales`)  
  WHEN CreateKal  
  THEN rollback de tota la transacció.

## Acceptance criteria

- [ ] `inviteToken` existeix al Domini i es genera a CreateKal; no és null
      després de crear.
- [ ] `Meeting` modelat al Domini (entitat + col·lecció) amb invariants
      mínimes (url https, timezone per defecte).
- [ ] Existeix `KalRepositoryInterface` + adaptador DBAL amb transacció
      que desa Kal (+ meetings si n’hi ha), **sense** `debate_room`.
- [ ] Command/handler CreateKal cobert amb test (unitari de handler amb
      repo fake, i/o integració local si l’entorn Supabase local està
      disponible).
- [ ] Cap missatge de debate passa pel PHP en aquest tall.
- [ ] Spec [`kal-aggregate-root.md`](kal-aggregate-root.md) es considera
      històric per a la primera versió Domain; aquest document és la font
      per al “done” MVP de l’agregat (o s’hi afegeix un enllaç “superseded
      in part by”).

## Constraints

- Performance: N/A (creació puntual).
- Security/permissions: CreateKal només organitzadora autenticada (detall
  d’auth a Shared/Security; no reinventar aquí).
- Compatibility: namespace `App\Kal\…` (no `App\Kals`); ULIDs text 26;
  errors com a codis.
- Stack: Doctrine DBAL SQL directe, sense ORM; migracions Supabase SQL.

## Out of scope

- Join per invitació, llista de participants, expulsió.
- Missatges de debate, Realtime, pujada d’imatges al xat.
- PatternInfo jsonb ric / craft / gauge, etc.
- Soft delete (`deletedAt`) exposat i RLS d’esborrats (pot preparar-se
  columna a migració, sense API).
- Múltiples debate rooms / fils.

## Trade-offs

- Chosen: Completar Domini mínim + CreateKal **sense** side-effect de
  `debate_room`; el xat es cableja quan es validi com a MVP.
- Benefit: CreateKal no arrossega un producte encara no decidit; menys
  files òrfenes i menys acoblament.
- Cost: Quan el xat arribi cal insert al create **i** backfill dels KALs
  ja creats sense aula.

## Risks and assumptions

- Assumption: Les taules `kals` / relacionades i helpers RLS
  (`is_kal_member`, …) existeixen o es creen a la mateixa entrega de
  migració que CreateKal; si encara no hi ha migracions al repo, la
  primera tasca és l’esquema base Kal (debate_rooms pot existir a
  l’esquema sense omplir-se al create).
- Assumption: `Files`/`Locales` actuals al Domini són la base acceptada
  (més rics que [`kal-aggregate-root.md`](kal-aggregate-root.md)); no es
  reverteix a `coverPath`-only.
- Risk: KALs creats abans del cablejat del xat no tenen `debate_room` →
  cal backfill quan el xat entri (ordre: CreateKal → Participation →
  FE xat + aula).
- Risk: Spec de Meeting vs “només URL a CreateKal” — si es vol encara més
  prim, Meeting es pot ajornar a la tasca just després; P1 obert.

## Open questions

- P0: Existeix ja l’esquema Supabase de `kals` en algun repo/migració
  externa, o cal crear-ho de zero en aquest backend?
- P1: Meeting entra al mateix PR que CreateKal o al següent?
- P1: CreateKal exposa HTTP en aquest tall o només Application + repo
  (UI més tard)?

## Verification

- Tests de Domini existents segueixen verds; nous tests per
  `inviteToken` i `Meeting`.
- Test de repositori/handler: després de CreateKal, 1 fila kal (integració)
  o assert de crides en fake; **cap** fila `debate_rooms`.
- Revisió: cap dependència Domain → Infrastructure; arch tests
  `App\Kal\Domain` coberts.
