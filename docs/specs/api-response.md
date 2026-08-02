# Spec: ApiResponse — contracte HTTP d’èxit (i errors deferred)

> Status: **Ready for implementation** (primer increment: helpers `ApiHttp*`).
> Branch prevista: `feat/KAL-005-response`.
> Supersedeix: [`kal-http-response-and-errors.md`](kal-http-response-and-errors.md).
>
> Escrit 2026-08-02. Decisions tancades amb l’arquitecta: writes sense payload
> útil (`201` → `{}`, `204` → cos buit), FE envia l’ULID al create, reads amb
> envelope `data`, col·lecció tipada (base abstracta), mapatge d’excepcions
> **pendent**.

---

## Problem

Avui `POST /kal` respon `201` amb `JsonResponse` cru i cos `{}`; els errors de
payload es munten a mà al controller (`{"error":"<codi>"}`), i les excepcions
de domini pugen al kernel com a `500`. Auth (KAL-004) ja exposa errors plans
`{"error":"<codi>"}` via `SupabaseAuthenticationEntryPoint`.

Sense un contracte compartit d’èxit:

- cada controller inventarà status/body diferents;
- el GET (següent tasca) no tindrà envelope estable per al FE / OpenAPI / MCP;
- el mapatge d’errors de domini (listener) encara no existeix, però cal no
  barrejar-lo amb el cablejat dels helpers d’èxit.

## Goals

- Fixar **una col·lecció tipada** de respostes HTTP d’èxit a Shared, usable des
  de qualsevol controller de context.
- Writes (commands): èxit = status + sense payload útil (`201` → `{}`,
  `204` → cos buit); el client ja coneix l’id si l’ha enviat.
- Reads (queries): èxit = `200` + envelope `{"data": ...}` (i `metadata`
  opcional quan el port de query ho suporti).
- Cablejar `POST /kal` a `ApiHttpCreatedResponse` com a primera adopció.
- Documentar el contracte d’error objectiu i el listener com a **Deferred**,
  sense bloquejar aquest increment.

## Non-Goals

- Implementar el listener `kernel.exception` / mapatge d’excepcions de domini
  (Deferred — veure secció dedicada).
- `GET /kal/{id}` ni cap altre endpoint de lectura (tasca posterior; només deixa
  el tipus de resposta a punt).
- E2E / OpenAPI / Nelmio.
- Canviar auth (ja correcte a KAL-004).
- Fer que els command handlers retornin valors (segueixen `void`).
- Envelope al health `GET /` (no és API de domini).

## Behavior

### Writes (commands)

- Èxit de **create**: `201`, body `{}` → `ApiHttpCreatedResponse`.
- Èxit d’**update/delete** sense payload de retorn: `204`, cos HTTP buit →
  `ApiHttpNoContentResponse`.
- El **client envia l’ULID** del recurs creat (p.ex. camp `id` al body de
  `POST /kal`). El servidor no retorna l’id ni `Location`.
- Fallades: forma objectiu `{"error":"<codi>"}` (igual que auth). En aquest
  increment, els errors de forma del payload poden seguir al controller; les
  invariants de domini poden continuar sortint com a `500` fins al Deferred.

### Reads (queries)

- Èxit: `200` → `ApiHttpOkResponse` amb body:

```json
{ "data": { /* ResponseInterface::result() */ } }
```

- Si en el futur el port de query exposa metadades, s’afegeix `metadata` al
  mateix nivell quan no sigui buit. Avui
  `App\Shared\Application\Query\ResponseInterface` només té `result()` —
  **no** cal estendre’l en aquest increment.

### Col·lecció tipada (Shared, capa Symfony UI/Infrastructure)

| Classe | Status | Body | Ús |
| --- | --- | --- | --- |
| `ApiHttpResponse` | — | base **abstracta** (encoding JSON) | no instanciar; només heretar |
| `ApiHttpCreatedResponse` | 201 | `{}` (JSON buit d’objecte) | creates |
| `ApiHttpNoContentResponse` | 204 | cos HTTP buit | updates/deletes |
| `ApiHttpOkResponse` | 200 | `{"data":...}` | queries |

- **No** existeix `ApiHttpOkCommandResult` ni variants de command amb body:
  els commands són `void`.
- Domain i Application **no** depenen d’aquestes classes (només UI).

### CreateKal (adopció mínima)

- El payload de `POST /kal` **exigeix** `id` (ULID vàlid, 26 chars).
- Es passa a `CreateKalCommand` / agregat (deixar de generar l’ULID només dins
  de `Kal::create()` quan entri aquest id — detall d’implementació, invariant:
  l’id del recurs creat és el que ha enviat el client).
- Resposta d’èxit: `return new ApiHttpCreatedResponse();` (substitueix
  `new JsonResponse(null, 201)`).

## Inputs / Outputs

### Inputs (create)

| Input | Source | Format | Required? |
| --- | --- | --- | --- |
| `id` | JSON body | ULID text 26 | yes (nou) |
| resta de camps create | JSON body | com avui | com avui |
| Bearer JWT | header | Supabase access token | yes (KAL-004) |

### Outputs

| Cas | Status | Body |
| --- | --- | --- |
| Create OK | 201 | `{}` |
| Update/delete OK (futur) | 204 | cos buit |
| Query OK (futur GET) | 200 | `{"data":...}` |
| Error auth | 401/503 | `{"error":"<codi>"}` (ja existeix) |
| Error domini / payload (objectiu) | 4xx/5xx | `{"error":"<codi>"}` (Deferred per al mapatge global) |

## Scenarios

### Happy path — create

- GIVEN un JWT vàlid i un body amb `id` ULID nou i camps de create vàlids
- WHEN `POST /kal`
- THEN `201` amb cos `{}`; el KAL persistit té aquest `id`

### Happy path — query (contracte; endpoint encara no)

- GIVEN una query que retorna `ResponseInterface`
- WHEN el controller fa `new ApiHttpOkResponse($response)`
- THEN `200` i `{"data": <result()>}`

### Edge — id absent o mal format al create

- GIVEN body sense `id` o amb id no ULID
- WHEN `POST /kal`
- THEN `400` amb codi estable (p.ex. `kal_invalid_payload` o el codi que ja usi
  la validació de forma); **no** crear el recurs

### Edge — id duplicat (Deferred / millora)

- GIVEN un `id` que ja existeix a `kals`
- WHEN `POST /kal`
- THEN objectiu: `409` + `kal_already_exists`. Fins al Deferred pot seguir
  sent `kal_persistence_failed` / 500

### Error — invariant de domini (Deferred)

- GIVEN `endsOn` anterior a `startsOn`
- WHEN `POST /kal`
- THEN objectiu: `400` + `kal_invalid_date_range`. Avui: 500 fins al listener

## Acceptance criteria (primer increment)

- [ ] Existeixen `ApiHttpResponse` (**abstracta**), `ApiHttpCreatedResponse`,
      `ApiHttpNoContentResponse`, `ApiHttpOkResponse` sota Shared (namespace
      Symfony UI/Response o equivalent acordat a implementació).
- [ ] `POST /kal` d’èxit retorna `ApiHttpCreatedResponse` (`201`, body `{}`).
- [ ] `POST /kal` exigeix `id` ULID al body i el persisteix com a id del KAL.
- [ ] `ApiHttpOkResponse` construeix `{"data": ...}` des de
      `ResponseInterface::result()` (test unitari; encara sense endpoint GET).
- [ ] Cap classe nova a Domain/Application depèn de Symfony HTTP Foundation
      per aquest contracte.
- [ ] `make qa` verd.
- [ ] El mapatge `kernel.exception` **no** forma part d’aquest PR.

## Constraints

- Hexagonal: helpers només a Shared Infrastructure/UI Symfony.
- CQRS: `CommandBusInterface::dispatch` segueix `void`; no retornar id des del
  handler.
- Errors com a **codis**, mai frases (quan s’exposin).
- PHPStan level max + `@throws` on calgui; CS Fixer `@PER-CS` + `@Symfony`.
- Coherència amb auth: body d’error pla `{"error":"..."}` quan arribi el
  listener.

## Out of scope / Deferred

### Deferred — mapatge d’excepcions (següent tasca)

- Listener `kernel.exception` a Shared Infrastructure.
- `DomainException` (i filles amb missatge = codi) → 400 + `{"error":"<codi>"}`.
- `*_persistence_failed` → 500 (codi de persistència o `internal_error` —
  decidir a la tasca Deferred).
- `InvalidArgumentException` de Shared amb prosa → `invalid_argument` genèric
  (P1: normalitzar tots els missatges a codis).
- Opcional: `ApiHttpErrorResponse` compartit amb auth.
- `409` + `kal_already_exists` per PK duplicada.

### Fora d’aquesta línia

- GET / update / soft-delete de KAL.
- E2E, OpenAPI.
- Canvis al contracte d’auth.

## Trade-offs

- **Chosen:** FE mint l’ULID + 201 buit, en lloc de `Location` o body amb `id`.
- **Benefit:** commands `void`, client simple, sense dependre del GET per
  navegar després del create.
- **Cost:** cal validar unicitat/format al servidor; el client ha de generar
  ULIDs correctament; conflictes d’id necessiten un 409 clar (Deferred).

- **Chosen:** col·lecció tipada vs un sol `ApiResponse($data, $status)`.
- **Benefit:** el tipus impedeix create amb body o GET sense envelope.
- **Cost:** més classes; cal disciplina per no afegir variants “per si de cas”.

## Risks and assumptions

- **Assumption:** el FE pot generar ULIDs (Crockford base32, 26 chars) abans del
  `POST`.
- **Risk:** sense 409 encara, un retry amb el mateix id pot ser 500 opac —
  mitigat documentant Deferred i acceptant-ho temporalment.
- **Assumption:** `ResponseInterface::result()` és suficient per al primer GET;
  `metadata` es pot afegir després sense trencar `data`.

## Open questions

- P1: cal estendre `ResponseInterface` amb `metadata(): array` abans del primer
  GET, o només quan hi hagi un cas real (paginació, etc.)?
- P1: al Deferred, `kal_persistence_failed` al body del 500 o només
  `internal_error` + detall al log?
- P1: el `try/catch` de payload a `KalCreateController` es queda o es mou al
  listener quan existeixi?

## Hand-off

Spec a [`docs/specs/api-response.md`](api-response.md). Següent pas:
`start-task` sobre la branca `feat/KAL-005-response` per implementar els
helpers + `id` al create. Després: tasca Deferred (listener) → GET → e2e.
