# Spec: GET /kal/{id}

> Status: **Approved**
> Escrit 2026-08-03 · Aprovat 2026-08-04. Completa el hand-off de
> [`api-response.md`](api-response.md) (reads amb envelope `data`) i exposa
> l’`inviteToken` generat al create.

---

## Problem

Després de `POST /kal` (201 `{}`), l’organitzadora no pot llegir el KAL ni
obtenir l’`inviteToken` (secret opac generat al servidor) per compartir
l’enllaç d’invitació. Sense un GET, la feature 2 (join) i la UI d’edició /
“sala” no tenen contracte de lectura.

## Goals

- `GET /kal/{id}` autenticat → `200` + `{"data": …}` amb la vista
  **organitzadora**, incloent `inviteToken`.
- No existeix / soft-deleted / no organitzadora → `404`
  `{"error":"Kal not found.","code":"kal_not_found"}` (mateix cos; no filtrar
  existència).
- Port de lectura (`findById` + reconstrucció) i mapatge 404 al subscriber
  d’excepcions per tipus (`NotFoundException`).

## Non-Goals

- Vista participant / member (camps reduïts, pistes no alliberades).
- Llistat “els meus KALs” / participacions / join per token.
- Missatges de xat via Symfony (Supabase + RLS).
- OpenAPI / E2E / PATCH / soft-delete explícit com a endpoint.
- Canviar el `201 {}` del create.

## Behavior

- `GET /kal/{id}` — JWT obligatori (firewall actual).
- Èxit: `200` + `ApiHttpOkResponse` →

```json
{
  "data": {
    "id": "<ulid>",
    "name": "...",
    "description": null,
    "startsOn": "...",
    "endsOn": null,
    "coverPath": null,
    "locales": ["ca", "es"],
    "inviteToken": "<opaque>",
    "meetings": [],
    "clues": [],
    "files": []
  }
}
```

  Sense `organizerId` a `data` (ja surt del token). Nested `meetings` /
  `clues` / `files`: la mateixa informació que ja es persisteix al create
  (mirallar el graf; sense inventar camps nous).

- Només l’**organitzadora** (`kals.organizer_id` = `profiles.id` del token).
- No existeix / soft-deleted (`deleted_at` no null) / no organitzadora →
  `404` `{"error":"Kal not found.","code":"kal_not_found"}`.
- Sense auth / token dolent → mateix contracte d’auth
  (`{"error":"<missatge>","code":"auth_*"}`); el GET no el redefineix.
- L’`inviteToken` **no** es regenera en el GET; es llegeix el valor persistit.
- Generació: ja cobreix `InviteToken::generate()` al create (opac, no JWT,
  sense `kal_id` / `organizer_id` embeguts). Aquest tall només l’**expansa**.

## Scenarios

#### Happy path
- GIVEN una organitzadora autenticada i un KAL seu existent  
  WHEN `GET /kal/{id}`  
  THEN `200` + `data` amb `inviteToken` no buit i camps coherents amb el create

#### Edge / error
- GIVEN sense `Authorization`  
  WHEN `GET /kal/{id}`  
  THEN `401` + `{"error":"Authentication token is missing.","code":"auth_token_missing"}`
- GIVEN usuària autenticada que **no** és l’organitzadora  
  WHEN `GET /kal/{id}`  
  THEN `404` + `{"error":"Kal not found.","code":"kal_not_found"}`
- GIVEN id inexistent o KAL soft-deleted  
  WHEN `GET /kal/{id}`  
  THEN `404` + `{"error":"Kal not found.","code":"kal_not_found"}`
- GIVEN id amb format no ULID  
  WHEN `GET /kal/{id}`  
  THEN `400` + `{"error":"The request payload is invalid.","code":"invalid_payload"}`

## Acceptance criteria

- [x] `GET /kal/{id}` existeix, protegit pel firewall, usa query bus +
      `ApiHttpOkResponse`.
- [x] Organitzadora rep `inviteToken` i la resta de `data` acordada
      (sense `organizerId`).
- [x] No-organitzadora / inexistent / soft-deleted → `404`
      `{"error":"Kal not found.","code":"kal_not_found"}` (mapper per tipus).
- [x] `KalRepositoryInterface` té lectura (`findById`) amb reconstrucció
      DBAL + doble in-memory; `deleted_at IS NULL`.
- [x] Tests HTTP (organitzadora OK, aliena 404, missing 404, soft-deleted
      404) + test del mapper per `NotFoundException` / `kal_not_found`.
- [x] Cap canvi al contracte `POST /kal` → `201 {}`.

## Out of scope

- Authz member / camps diferents per rol.
- `GET /kals` (llista).
- Join, expulsió, galeria, recordatoris.
- Regenerar o rotar `inviteToken`.

## Decisions

- **Sense `organizerId` a `data`**: redundant amb el token; el FE ja el té.
- **Shape nested**: mirallar el payload/graf del create (meetings, clues amb
  file+meeting, files), no una vista mínima separada.
- **Errors**: cos dual `error` (llegible) + `code` (estable); status per
  `instanceof`, no pel text del missatge.

## Open questions

Cap.
