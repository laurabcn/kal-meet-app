# Spec: mapatge d’excepcions HTTP + alertes Slack

> Status: **Ready for implementation**
> Branch prevista: `feat/KAL-005-exceptions`
> Completa el Deferred de [`api-response.md`](api-response.md).
> Escrit 2026-08-03.

---

## Problem

Després de KAL-005, l’èxit HTTP està tipat (`ApiHttp*`), però les excepcions de
domini que pugen del `command.bus` encara cauen al kernel com a **500** HTML/JSON
de Symfony, sense `{"error":"<codi>"}`. Auth ja té forma estable via
`SupabaseAuthenticationEntryPoint`; el create encara fa `try/catch` local per
payload.

A més, `make logs` / `make logs-errors` només ajuden si algú mira la consola.
Cal un canal (`#alertas`) que avisi quan hi ha un 5xx sense estar pendent dels
logs (equivalent “push” al que feia Datadog a la feina, a escala MVP).

## Goals

- Un **únic** punt (`kernel.exception`) que tradueixi excepcions de domini /
  aplicació a HTTP + `{"error":"<codi>"}`.
- Desembolcallar `HandlerFailedException` de Messenger perquè el mapa vegi la
  causa real (`KalException`, …).
- Log estructurat (PSR-3 / Monolog JSON) segons la skill `logging`: **5xx
  sempre**; 4xx mapejats **no** per defecte.
- Handler Monolog → **Symfony Notifier + `symfony/slack-notifier`** cap a
  `#alertas`, només per `ERROR`/`CRITICAL` (els 5xx del subscriber).
- Detectar PK / id duplicat al create i respondre **`409` + `kal_already_exists`**
  (no 500 opac).
- `ApiHttpErrorResponse` (o equivalent) per a la forma d’error, alineada amb auth.

## Non-Goals

- Datadog / Sentry / APM (posterior; els JSON a stderr ja hi poden anar).
- Canviar el contracte d’auth (ja correcte).
- Normalitzar *tots* els missatges anglesos d’`InvalidArgumentException` de
  Shared a codis (P1 futur); aquest increment usa un codi genèric.
- GET /kal, e2e, OpenAPI.
- Alertar 4xx a Slack (soroll: payload mal format, etc.).
- Incoming Webhook manual / cURL a una URL: usem el bridge oficial de Symfony.

## Decisions tancades

| # | Decisió | Escollit |
| --- | --- | --- |
| 1 | Body del 500 de persistència | **A** — `{"error":"kal_persistence_failed"}` (o el codi de l’excepció `*persistence_failed`); detall de la causa només al log |
| 2 | `InvalidArgumentException` Shared (prosa) | **A** — `400` + `invalid_argument` |
| 3 | `try/catch` de payload a `KalCreateController` | **A** — es queda; el listener cobreix el que puja al kernel |
| 4 | Forma d’error | `{"error":"<codi>"}` via `ApiHttpErrorResponse` (mateixa forma que auth) |
| 5 | Arquitectura | `ApiExceptionMapper` (excepció → status + codi) + `ApiExceptionSubscriber` (kernel + log + response), inspirat en Nostromo |
| 6 | Slack | `symfony/notifier` + `symfony/slack-notifier`; DSN `SLACK_DSN`; Monolog → Notifier només `ERROR`+; canal `alertas` |
| 7 | 4xx al log | No per defecte (skill `logging`); 5xx sí |

## Behavior

### Flux

```
Throwable
  → unwrap HandlerFailedException (previous)
  → ApiExceptionMapper → (status, errorCode)
  → ApiHttpErrorResponse(status, errorCode)
  → si status >= 500: logger->error/critical (static message + context)
     → Monolog main (stderr JSON) + canal/handler Slack
```

### Mapatge (partida)

| Excepció / condició | Status | `error` |
| --- | --- | --- |
| `App\Shared\Domain\Exception\DomainException` i filles quan el missatge és un codi (p.ex. `kal_invalid_date_range`) | 400 | `getMessage()` |
| Missatge `*_persistence_failed` (o factory `persistenceFailed`) | 500 | el mateix codi (`kal_persistence_failed`, `user_persistence_failed`, …) |
| Violació d’unicitat / id ja existent (detectada al repositori o via SQLSTATE) | 409 | `kal_already_exists` |
| `App\Shared\Domain\Exception\InvalidArgumentException` | 400 | `invalid_argument` |
| `AuthenticationFailedException` (i filles) | *no tocar* — ja les gestiona el security entry point; el subscriber no les ha de reescriure si ja hi ha response, o les ha d’ignorar |
| Resta de `\Throwable` | 500 | `internal_error` |

El mapper ha de preferir **match per tipus + regles de codi** (persistence /
already_exists) abans que “tot `DomainException` → 400”.

### Logging (subscriber)

Missatge estàtic, p.ex. `Unhandled API exception`, context:

- `exception` (objecte; Monolog l’expandeix)
- `error` (codi o missatge)
- `http_status`
- `route`, `method` (no body, no `Authorization`)

Nivell: `error` o `critical` només quan `http_status >= 500`.

### Slack (Symfony Notifier)

- Paquets: `symfony/notifier`, `symfony/slack-notifier` (Flex recipe per
  `config/packages/notifier.yaml` si cal).
- Env: `SLACK_DSN=slack://TOKEN@default?channel=alertas`
  - `TOKEN` = Bot User OAuth Token (`xoxb-...`) d’una Slack App amb
    `chat:write`, **convidada** a `#alertas`.
  - `channel` = nom **sense** `#` (`alertas`), no l’ID obligatori.
  - Si `SLACK_DSN` és buit / no definit: no registrar el transport (o transport
    null); l’arrencada i els 500 **no** han de petar per Slack.
- Monolog: handler dedicat (p.ex. via `NotifierHandler` / bridge Monolog) amb
  `level: error` que enviï al chatter Slack; el handler `main` (stderr JSON)
  es manté.
- Contingut del missatge: curt i indexable, p.ex.  
  `*[KAL][500]* \`kal_persistence_failed\` · \`POST /kal\` · route=kal_create`
- No enviar stack traces sencers a Slack (queden al log JSON de stderr).
- Documentar a `.env.example` (`SLACK_DSN=`) i uns passos mínims al README
  (crear Slack App → token → invite a `#alertas` → DSN).

### `kal_already_exists`

- Preferible: el repositori / adaptador detecta duplicate key i llença una
  `KalException` (o Shared conflict) amb codi `kal_already_exists`.
- El mapper la tradueix a 409.
- Evitar que un retry del FE amb el mateix ULID sembli un 500 intern.

## Inputs / Outputs

| Input | Source |
| --- | --- |
| Qualsevol excepció no gestionada al kernel HTTP | Symfony |
| `SLACK_DSN` | env (secret; bot token + canal) |
| `LOG_LEVEL` | env (ja existeix) |

| Output | Destí |
| --- | --- |
| `{"error":"<codi>"}` + status | client HTTP |
| Línia JSON | stderr (`make logs` / `make logs-errors`) |
| Missatge curt | Slack `#alertas` (només 5xx) |

## Scenarios

### Happy — invariant de domini

- GIVEN `endsOn` < `startsOn` al create
- WHEN `POST /kal`
- THEN `400` + `{"error":"kal_invalid_date_range"}`; **sense** missatge Slack;
  **sense** log d’error (4xx mapejat)

### Happy — persistència

- GIVEN la BD no accessible / error SQL genèric
- WHEN create
- THEN `500` + `{"error":"kal_persistence_failed"}`; log ERROR a stderr; missatge
  a `#alertas` si `SLACK_DSN` està configurat

### Happy — id duplicat

- GIVEN un `id` ULID ja present a `kals`
- WHEN `POST /kal`
- THEN `409` + `{"error":"kal_already_exists"}`; sense alerta Slack

### Edge — DSN buit

- GIVEN `SLACK_DSN` no definit
- WHEN un 500
- THEN la resposta HTTP i el log stderr són correctes; cap error d’arrencada
  ni excepció secundària per Slack

### Edge — Messenger wrap

- GIVEN `KalException` dins `HandlerFailedException`
- WHEN puja al kernel
- THEN el client veu el codi de `KalException`, no un 500 genèric pel wrapper

## Acceptance criteria

- [ ] `ApiExceptionMapper` + `ApiExceptionSubscriber` a Shared Infrastructure.
- [ ] Unwrap de `HandlerFailedException`.
- [ ] Respostes d’error amb `{"error":"<codi>"}` (`ApiHttpErrorResponse`).
- [ ] Domain invariants al create → 400 + codi (test HTTP o kernel).
- [ ] `*_persistence_failed` → 500 + mateix codi + log ERROR.
- [ ] Id duplicat → 409 `kal_already_exists`.
- [ ] `InvalidArgumentException` Shared no controlada → 400 `invalid_argument`.
- [ ] Slack: amb `SLACK_DSN` configurat, un 500 provoca una notificació via
      Notifier (test amb transport/chatter mock); sense DSN, no peta.
- [ ] Cap body de request ni header `Authorization` als logs ni a Slack.
- [ ] `make logs` / `make logs-errors` documentats (ja al Makefile).
- [ ] `.env.example` amb `SLACK_DSN=` (comentari: bot token + canal `alertas`).
- [ ] `make qa` verd.
- [ ] Auth 401/503 **no** regreden (entry point segueix manant).

## Constraints

- Hexagonal: mapper/subscriber només a Shared Infrastructure Symfony.
- Errors = **codis**, mai frases al body HTTP.
- Skill `logging`: missatge estàtic + context; no catch-log-rethrow duplicat.
- Secrets només a `.env.local` / secrets del hosting, mai al Git.
- PHPStan `@throws` / CS Fixer com sempre.

## Out of scope

- Sentry, Datadog agent, OpenTelemetry.
- Alertes per 4xx.
- Normalització completa de prosa anglesa als VOs Shared.
- Treure el `try/catch` de payload del create (queda 3.A).

## Trade-offs

- **Chosen:** codi de persistència al body del 500 (no només `internal_error`).
- **Benefit:** el FE pot mostrar / traduir “reintenta” vs error desconegut.
- **Cost:** s’exposa un codi tècnic; acceptable (ja és convenció del producte).

- **Chosen:** Symfony Notifier + `slack-notifier` (bot token), no Incoming
  Webhook manual.
- **Benefit:** stack oficial, mateix component que podrà portar email/SMS; DSN
  estàndard; tests amb transport mock.
- **Cost:** cal crear una Slack App i convidar-la al canal (un cop).

- **Chosen:** Slack només per 5xx via Monolog ERROR+, no cada warning.
- **Benefit:** `#alertas` no s’omple de 400s de payload.
- **Cost:** sense grouping/dedup; rate limits si hi ha bucle d’errors.

## Risks and assumptions

- **Assumption:** es pot crear una Slack App amb `chat:write`, instal·lar-la al
  workspace i convidar el bot a `#alertas`.
- **Risk:** spam a Slack si un endpoint falla en bucle — mitigar: només 5xx del
  subscriber HTTP (no cada warning JWKS) en aquest increment.
- **Risk:** false 409 si es confon qualsevol SQLSTATE — cal mapatge estret a
  unique violation de `kals_pkey` / equivalent.

## Open questions (P1, no bloquejen)

- Agrupar / throttling de Slack més endavant?
- Moure payload `try/catch` del create al listener en un refactor posterior?

## Hand-off

Spec a [`docs/specs/http-exception-mapping.md`](http-exception-mapping.md).
Següent: `start-task` a `feat/KAL-005-exceptions` → implementar → review → PR.
