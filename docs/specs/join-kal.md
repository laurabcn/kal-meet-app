# Spec: POST /kal/participation (apuntar-se per invitació)

> Status: **Approved**
> Escrit 2026-08-04 · Aprovat 2026-08-04 · Ruta alineada amb la
> implementació 2026-08-04 (`/kal/participation`; abans es va acordar
> `/kal/join`). Completa la feature 2 de l’MVP després de
> [`get-kal.md`](get-kal.md) (l’organitzadora ja pot llegir `inviteToken`).
> Decisions de contracte tancades en xat abans d’escriure.

---

## Problem

L’organitzadora pot crear un KAL i obtenir l’`inviteToken`, però una
participant autenticada encara no es pot apuntar. Sense fila a
`participations`, no hi ha membres reals: `is_kal_member` és un stub (només
organitzadora), i fotos / xat / `GET /kals/mine` no tenen base.

## Goals

- `POST /kal/participation` autenticat: la usuària s’apunta a un KAL enviant
  `kalId` + `inviteToken` (el client els treu del deep link).
- Èxit → `201 {}` (mateix cos buit que `POST /kal`).
- Persistir una participació activa i actualitzar `is_kal_member` perquè
  reconegui organitzadora **o** participació activa.
- Errors estables: `404` + `kal_not_found` · `409` + `kal_already_member`.

## Non-Goals

- Vista participant de `GET /kal/{id}` (camps reduïts, pistes no alliberades).
- Llistat `GET /kals/mine` / participacions.
- Expulsió / re-join després d’expulsió.
- Regenerar o rotar `inviteToken`.
- Missatges de xat (`debate_messages`). L’aula (`debate_rooms`) la crea
  `CreateKal`, no aquest endpoint.
- OpenAPI / E2E / canviar el contracte de create o get.

## Behavior

- `POST /kal/participation` — JWT obligatori (firewall actual).
- Body JSON:

```json
{
  "kalId": "<ulid>",
  "inviteToken": "<opaque>"
}
```

- Flux (aplicació):
  1. Resoldre `profiles.id` de la usuària autenticada (ja com al create/get).
  2. Carregar el Kal per `kalId` amb `deleted_at IS NULL` i verificar que
     `inviteToken` coincideix (`findByToken`).
  3. Si no existeix / soft-deleted / token ≠ persistit → `404`
     `kal_not_found` (mateix cos; no filtrar “token dolent” vs “kal
     inexistent”).
  4. Si la usuària és l’organitzadora (`organizer_id`) **o** ja té
     participació → `409` `kal_already_member`.
  5. Altrament insert a `participations` via `CreateParticipationCommand`
     → `201 {}`.

- El `kalId` el porta el client (deep link). El backend **no** resol el KAL
  només amb el token.
- Auth fallida → mateix contracte d’auth existent (`auth_*`); aquest tall no
  el redefineix.

### Esquema (`participations`)

Migració nova (no editar a cegues l’històric sense necessitat):

| Columna | Tipus | Notes |
|---------|--------|--------|
| `id` | text PK ULID 26 | Generat a l’aplicació |
| `kal_id` | text FK → `kals(id)` | Cascade on delete |
| `user_id` | text FK → `profiles(id)` | Id intern, no `auth.uid()` |
| `joined_at` | timestamptz | Default `now()` |

- Unique `(kal_id, user_id)`.
- Sense columna `status` en aquest tall (expulsió = tasca posterior).

### RLS helper

Actualitzar `is_kal_member(p_kal_id)`: organitzadora del KAL no esborrat **o**
existeix fila a `participations` per `profiles.id` lligat a `auth.uid()` via
`profiles.external_id`.

## Inputs

| Input | Source | Format | Required? |
|-------|--------|--------|-----------|
| JWT | Header `Authorization` | Bearer Supabase | yes |
| kalId | Body (deep link FE) | ULID 26 | yes |
| inviteToken | Body (deep link FE) | non-empty opaque string | yes |

## Outputs

| Output | Consumer | Format |
|--------|----------|--------|
| Èxit nou join | FE | `201` + `{}` |
| Ja membre / organitzadora | FE | `409` + `{"error":"…","code":"kal_already_member"}` |
| Kal / token invàlid | FE | `404` + `{"error":"Kal not found.","code":"kal_not_found"}` |
| Payload invàlid | FE | `400` + `invalid_payload` / `InvalidArgumentException` |
| Fila `participations` | BD / RLS / talls posteriors | `kal_id` + `user_id` |

## Scenarios

#### Happy path

- GIVEN una usuària autenticada que no és organitzadora ni membre, i un KAL
  existent amb `inviteToken` T  
  WHEN `POST /kal/participation` amb `{ "kalId": "<id>", "inviteToken": "T" }`  
  THEN `201 {}` i existeix una fila a `participations` per `(kalId, userId)`

#### Edge / error

- GIVEN sense `Authorization`  
  WHEN `POST /kal/participation`  
  THEN `401` + `auth_token_missing` (contracte auth existent)

- GIVEN organitzadora del KAL  
  WHEN `POST /kal/participation` amb token correcte  
  THEN `409` + `kal_already_member` (cap fila nova a `participations`)

- GIVEN ja hi ha participació per aquesta usuària  
  WHEN `POST /kal/participation` amb token correcte  
  THEN `409` + `kal_already_member`

- GIVEN `kalId` inexistent o soft-deleted  
  WHEN `POST /kal/participation`  
  THEN `404` + `kal_not_found`

- GIVEN `kalId` existent però `inviteToken` incorrecte  
  WHEN `POST /kal/participation`  
  THEN `404` + `kal_not_found` (mateix cos que inexistent)

- GIVEN `kalId` amb format no ULID o body sense camps  
  WHEN `POST /kal/participation`  
  THEN `400` + codi d’`InvalidArgumentException` / payload invàlid (mateix
  patró que create/get)

## Acceptance criteria

- [x] `POST /kal/participation` existeix, protegit pel firewall, despacha
      `CreateParticipationCommand` pel `command.bus`.
- [x] Èxit → `201 {}`; es crea exactamente una fila `participations`.
- [x] Organitzadora o ja membre → `409`
      `{"error":"…","code":"kal_already_member"}` via `ConflictException`
      (o subclasse).
- [x] Inexistent / soft-deleted / token ≠ persistit → `404`
      `{"error":"Kal not found.","code":"kal_not_found"}`.
- [x] Migració: taula `participations` + `is_kal_member` deixa de ser stub.
- [x] Tests HTTP (happy, 409 organitzadora, 409 membre, 404 missing, 404
      soft-delete, 404 bad token, 400 bad payload) + `make qa`; persistència
      coberta amb `make test-db` quan l’entorn local hi és.

## Constraints

- Performance: N/A (join puntual).
- Security: JWT obligatori; `user_id` = `profiles.id` intern; mai confiar en
  un id enviat pel client com a “qui sóc”. Token d’invitació opac, no a la
  URL de l’API (va al body).
- Compatibility: cos d’error dual `error` + `code`; status per `instanceof`
  (`NotFoundException` / `ConflictException`). ULIDs 26 chars app-side.
- Stack: DBAL SQL directe; migració Supabase SQL.

## Out of scope

- Authz de lectura per membres al GET.
- Expulsió, `status` a participations, re-join.
- `GET /kals/mine`, galeria, recordatoris, xat.
- Canviar generació o forma de l’`inviteToken`.

## Trade-offs

- Chosen: ruta `POST /kal/participation` (recurs Participation), no
  `/kal/join` (verb). Cos i errors idèntics al contracte aprovat.
- Benefit: alineat amb create (`POST /kal`) i amb el nom del command /
  taula; el FE i Postman usen el mateix path.
- Cost: el deep link FE pot seguir dient `/join/…` a la UI; només canvia
  l’endpoint backend.

- Chosen: el client envia `kalId` + token; resposta `201 {}`.
- Benefit: alineat amb create; deep link FE senzill; el backend no exposa
  “lookup per token sol”.
- Cost: el FE ha de portar el `kalId` a l’URL pública d’invitació (l’id no és
  secret; el secret continua sent el token).

- Chosen: `409` (no idempotent) si ja és membre / organitzadora.
- Benefit: el FE pot distingir “acaba d’entrar” vs “ja hi era”.
- Cost: un doble tap a l’enllaç mostra error en lloc de silenci; acceptable
  a l’MVP.

## Risks and assumptions

- Assumption: el deep link FE és del tipus
  `/join/:kalId?token=…` (o equivalent) abans o en paral·lel a aquest tall.
- Assumption: `ConflictException` ja mapeja a 409 al
  `ApiExceptionMapper` (com `KalAlreadyExistsException`).
- Risk: race de dos joins paral·lels → unique `(kal_id, user_id)` a BD;
  el repositori ha de traduir violació d’unicitat a `kal_already_member`
  (no 500).
- Risk: sense vista member al GET, després del join el FE encara no pot
  “obrir la sala” via `GET /kal/{id}` (404 per no-organitzadora). Cal tall
  posterior (vista member o list mine) abans del loop família complet.

## Open questions

Cap (contracte tancat abans d’escriure).
