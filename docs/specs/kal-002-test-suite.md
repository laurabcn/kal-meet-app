# Spec: KAL-002 test suite (functional · acceptance · e2e)

> Status: **COMPLETE — all 13 sections written.** 6 open questions in §13 need the
> architect's call; none blocks Increment A (§8.1).
> Scope: branch `KAL-002` (the `CreateKal` write flow + the `Kal` aggregate,
> value objects and `DbalKalRepository` already written on this branch).
> This is a **test spec**: it defines which tests must exist, at which layer,
> with which scenarios and acceptance criteria — it is a work order for writing
> the tests, not the tests themselves.

---

## 1. Problem & context

Branch `KAL-002` builds the first real write use case of the product: creating a
KAL. The code already on the branch is **unevenly tested**:

- **Domain** (`src/Kal/Domain`) — well covered by fast unit tests with Object
  Mothers (`KalTest`, `MeetingTest`, `ClueTest`, `LocalesTest`, `FileSizeTest`,
  plus `Shared` VO tests).
- **Application** (`CreateKalHandler`) — covered by `CreateKalHandlerTest`, but
  only against an **in-memory** repository double (`InMemoryKalRepository`). The
  handler's orchestration is exercised; the real SQL path is not.
- **Infrastructure** (`DbalKalRepository`) — **zero tests**. The real SQL writes
  (six inserts across six tables, explicit transaction, rollback on failure)
  have never run under test.
- **Ui** — **does not exist**. There is no `POST /kals` endpoint; the HTTP
  skeleton only serves `HealthController`. The command bus is wired
  (`command.bus`, `SymfonyCommandBus`) but nothing dispatches `CreateKalCommand`
  from HTTP.

Consequently there is no test that proves a KAL can be created **the way a client
will really do it** (HTTP request → response), and the handler's wiring through
the real command bus is unproven.

This spec closes those gaps by defining a **three-tier, fully in-memory** test
suite for the `CreateKal` flow. **Decision (architect):** the whole suite is
hermetic — no external database. The real SQL path (`DbalKalRepository`) is
**deliberately left untested** to keep the suite fast and free of any Supabase
dependency; this is an accepted trade-off, recorded in §11 and §12, not a gap
this suite closes. What the suite *does* close: the request→command translation
(HTTP contract) and the command-bus wiring, on top of the already-solid domain
coverage.

## 2. Goals / Non-Goals

### Goals

- Define three test tiers for the `CreateKal` flow, with a **precise, repo-
  specific boundary** for each (see §3), so "functional / acceptance / e2e" are
  unambiguous in this codebase from now on.
- Define the **in-memory test harness** the acceptance and e2e tiers need: the
  test kernel, the `services_test` swap of `KalRepositoryInterface` to the
  in-memory double, and reuse of the domain Object Mothers — none exists today.
- Define the **`POST /kals` endpoint contract** that the e2e tier requires as a
  dependency (request/response/status/error mapping). Building the endpoint is a
  separate implementation task; this spec only fixes its contract so the e2e
  scenarios are writable and unambiguous.
- Enumerate the **coverage gaps** in the KAL-002 code already written, so the
  test-writing task has a checklist, not a vibe.
- Give explicit, testable **acceptance criteria** (definition of done) for the
  suite.

### Non-Goals

- Not writing the tests here (that is the implementation task that follows).
- Not implementing the `POST /kals` controller (separate task; only its contract
  is fixed here).
- Not testing **RLS policies**. The backend uses the Supabase `service_role`
  key and **bypasses RLS**; RLS is the frontend's direct-access concern and is
  validated separately (pgTAP / Supabase-side), out of scope for this suite.
- Not testing read/query flows, update, or soft-delete — KAL-002 is create-only.
- Not testing auth/JWT verification as a subsystem (see §5 for how the endpoint
  handles identity, and Open Questions for what the e2e assumes).
- No CI wiring (there is no CI yet); the gate remains local `make qa` / `make test`.

## 3. Test taxonomy (definitions for this repo)

Three tiers, each defined by **where it enters the system** and **what it
touches**. This mapping is the reference definition for the whole backend, not
just `CreateKal`.

**Decision (architect):** no tier touches a database. Every tier substitutes
`KalRepositoryInterface` with `InMemoryKalRepository`. The tiers are therefore
distinguished by **how far up the stack the test enters**, not by what they
persist to:

| Tier | Entry point | Real collaborators | Kernel | DB | Speed | Lives in |
|---|---|---|---|---|---|---|
| **Functional** | `CreateKalHandler::__invoke()` called directly, handler built with `new` | Real handler + real domain; `InMemoryKalRepository` injected by hand | none | none | fast (ms) | `tests/Kal/Application/...` |
| **Acceptance** | `CommandBusInterface::dispatch(new CreateKalCommand(...))` | Real `command.bus` + `MessageStoreMiddleware` + handler resolution + **real DI wiring**; `InMemoryKalRepository` swapped via `services_test` | booted | none | medium (boot) | `tests/Kal/Application/...` (or `tests/Acceptance/...` — see §13) |
| **E2E** | HTTP `POST /kals` via `KernelBrowser` | Real routing → real controller → real `command.bus` → real handler; `InMemoryKalRepository` swapped via `services_test` | booted | none | slowest (HTTP) | `tests/Kal/Ui/...` (or `tests/E2E/...` — see §13) |

Boundary rules that make each tier honest:

- **Functional** proves *orchestration and domain invariants* in isolation. It is
  the current `CreateKalHandlerTest`, kept as-is and treated as the functional
  tier. No container, no bus, no HTTP.
- **Acceptance** proves *the container wiring is correct*: that
  `CreateKalHandler` is discoverable on `command.bus` via its
  `#[AsMessageHandler(bus: 'command.bus')]` attribute, that dispatching the
  command reaches it exactly once, and that the middleware chain does not choke
  on a non-`Storable` command. This tier exists because `config/services.yaml`
  binds the four buses **by parameter name** (`$commandBus`, `$queryBus`, …),
  not by type — a silent misbinding is invisible to the functional tier and
  expensive to debug at the e2e tier.
- **E2E** proves *a real client can create a KAL the real way*: an HTTP request
  in, the correct HTTP response out, and the aggregate handed to the repository.
  It goes through routing, request→command deserialization/validation, and the
  bus. It requires the `POST /kals` endpoint (§5), which does not exist yet.

Rationale for keeping acceptance as its own tier rather than collapsing into
e2e: the exhaustive **command→aggregate matrix** (all optional collections
present/absent, every domain error code) is cheaper and clearer dispatched
straight onto the bus, so e2e carries only a **thin slice** proving the HTTP
contract. See §6.

**Consequence to hold explicitly:** `DbalKalRepository` — the six inserts across
six tables, the explicit transaction and the rollback — is exercised by **no
tier of this suite**. The historical `created_at`-null bug from `CLAUDE.md`
cannot be caught here. Two mitigations, both recorded in §11/§12 rather than
solved by this spec: (a) `KalRepositoryInterface::create()` returns `void`, so
the specific "returns the in-memory object instead of the persisted row" shape
of that bug is not expressible through this port; (b) verifying the real SQL
path stays a **separate, deferred task** (integration tests against local
Supabase, or manual smoke), which must be opened before `POST /kals` ships to
any real client.

## 4. Test harness & infrastructure

The functional tier needs nothing new — it builds the handler with `new` and
passes `InMemoryKalRepository` by hand, which is what `CreateKalHandlerTest`
already does. **The acceptance and e2e tiers need a booted test kernel, and none
of that machinery exists today.** This section is the build order.

### 4.1 What exists today (verified against the repo)

| Piece | State |
|---|---|
| PHPUnit/Pest config | **None.** No `phpunit.xml.dist`, `phpunit.xml`, `pest.xml` or `tests/Pest.php`. Pest runs entirely on its defaults. |
| Test entry point | `make test` → `docker compose run --rm pest`. Suites are not declared, so there is no way to run one tier in isolation. |
| Dev dependencies | `pestphp/pest`, `friendsofphp/php-cs-fixer`, `phpstan/phpstan`. **No `symfony/browser-kit`** → `KernelBrowser` is unavailable, so the e2e tier cannot be written yet. |
| `framework.test` | Not set (`config/packages/framework.yaml` declares only `secret` + `router.utf8`). Without it the `test.client` service does not exist. |
| Test-env container overrides | **None.** No `config/services_test.yaml`, no `config/packages/test/`. |
| `Tests\` namespace in the container | Not registered. `services.yaml` only loads `../src/`, so `InMemoryKalRepository` is not a service and cannot be swapped in by autowiring alone. |
| `.env` | `APP_ENV=dev`, and `DATABASE_URL` points at the **remote** Supabase project — see §4.4. |

### 4.2 Build order

1. **`phpunit.xml.dist`** — the prerequisite for everything else. It must set
   `APP_ENV=test` and `KERNEL_CLASS=App\Kernel` as `<server>`/`<env>` vars, and
   declare one `<testsuite>` per tier so each can run alone:
   `arch` (`tests/Arch`), `domain` (`tests/Kal/Domain`, `tests/Shared`),
   `functional` + `acceptance` (`tests/Kal/Application`), `e2e` (`tests/Kal/Ui`).
   Add `make test-domain` / `make test-e2e` style targets only once the suites
   exist; do not invent Makefile targets ahead of them (CLAUDE.md is explicit
   that `make lint`/`typecheck`/`arch`/`check` do not exist and must not be
   cited as if they did).
2. **`.env.test`** — committed (no secrets), with a deliberately unreachable
   `DATABASE_URL`. This is a safety rail, not configuration: see §4.4.
3. **`config/packages/test/framework.yaml`** — `framework: { test: true }`.
   `MicroKernelTrait` already loads `config/packages/{env}/*.yaml` with no
   override needed, so the file is picked up as-is.
4. **`config/services_test.yaml`** — also auto-loaded by `MicroKernelTrait`
   (it loads `config/services_{env}.yaml` by convention), so again no kernel
   change. It must do two things:
   - register `Tests\Kal\Infrastructure\Persistence\InMemoryKalRepository` as a
     service explicitly (the `Tests\` PSR-4 root is `autoload-dev`, outside the
     `App\` resource glob);
   - alias `App\Kal\Domain\KalRepositoryInterface` to it, and mark the alias
     **`public: true`** so tests can pull the double out of the container to
     assert on what was persisted.
5. **`composer require --dev symfony/browser-kit`** — required by the e2e tier
   only. `symfony/css-selector` is *not* needed: `POST /kals` returns JSON, so
   assertions read the response body, not the DOM.
6. **`tests/Pest.php`** — bind the base test case per directory rather than
   per file, so a test's tier is implied by where it lives:
   `pest()->extends(KernelTestCase::class)->in('Kal/Application')` for
   acceptance, `pest()->extends(WebTestCase::class)->in('Kal/Ui')` for e2e.
   `KernelTestCase` and `WebTestCase` both ship with `symfony/framework-bundle`,
   which is already a runtime dependency.

Steps 1–4 unblock the acceptance tier; 5–6 unblock e2e. Both can land before
`POST /kals` exists (§5), since the acceptance tier does not need an endpoint.

### 4.3 The in-memory double

`InMemoryKalRepository` already exists and implements the one-method port
(`create(Kal $kal): void`). It also exposes three affordances that are **not** on
the interface — `findById()`, `all()` and `debateRoomsCreated()` — which is
correct for a test double, but two things need fixing before §6's scenarios are
writable:

- **No failure injection.** The port declares `@throws KalException`, and the
  handler propagates it, but the double can never throw — so "repository fails"
  is untestable at every tier. Add an explicit hook (e.g.
  `willFailWith(KalException $e)`) rather than a subclass-per-test.
- **`debateRoomsCreated()` is mislabelled.** It counts `create()` calls; it does
  not observe anything about debate rooms, because creating them is
  `DbalKalRepository`'s business and that class is out of this suite (§3). Either
  rename it to what it measures (`createCallCount()`) or drop it — as named it
  invites an assertion that would prove nothing.

Reuse the existing Object Mothers (`KalMother`, `ClueMother`, `MeetingMother`,
`FileMother`, `LocalesMother`, …) unchanged. The acceptance and e2e tiers,
however, need **command payloads**, not aggregates: `CreateKalCommand` takes
nested `array` shapes (see its `@param` annotations), so add a
`CreateKalCommandMother` that emits those arrays. Without it every acceptance
test hand-rolls a deeply nested fixture and the tier becomes unreadable.

### 4.4 Safety rail: the suite must not be able to reach the real database

This is the one piece of §4 that is a correctness requirement rather than
plumbing. `.env` currently sets `APP_ENV=dev` with `DATABASE_URL` pointing at
the **remote** Supabase project. A booted kernel that picks up the dev
environment therefore has production credentials in hand, which directly
contradicts CLAUDE.md's "els d'integració contra Supabase **local** (mai
producció)".

Today the `CreateKal` path happens to open no connection, and it is worth
recording *why*, because §6 depends on it:

- `CreateKalCommand` implements the plain `CommandInterface`, not
  `StorableCommandInterface`, so `MessageStoreMiddleware` short-circuits on its
  first `instanceof StorableMessageInterface` check;
- even for a storable message it would be inert, since no
  `MessageStoreRepositoryInterface` implementation exists anywhere in `src/` and
  `MessageStoreRepositoryMapper` defaults to an empty map, making `find()`
  always return `null`;
- `DbalKalRepository` is replaced by the double, and DBAL connections are lazy.

That is a property to **protect**, not to rely on: the first storable command or
the first query handler that reaches DBAL would silently start talking to
production from a test run. Two rails, both cheap:

1. `.env.test` sets `DATABASE_URL` to an unreachable value, so an accidental
   connection fails loudly instead of succeeding against production.
2. `tests/bootstrap.php` (wired via `phpunit.xml.dist`) asserts
   `APP_ENV === 'test'` and aborts otherwise.

Rail 1 is what makes the failure obvious; rail 2 is what makes it early.

## 5. `POST /kals` endpoint contract (e2e dependency)

This section fixes the contract only. **Building the endpoint is a separate task**
(§2, Non-Goals); what follows exists so the e2e scenarios in §6 are writable and
unambiguous. Three decisions here are genuinely the architect's, not derivable
from the code — they are marked **DECISION NEEDED** and repeated in §13.

### 5.1 Route, placement and wiring

`POST /kals`, `Content-Type: application/json`, JSON response.

Placement follows the per-context convention in CLAUDE.md (`Ui/` inside the
bounded context), not the current `src/Controller/`:
`src/Kal/Ui/Http/CreateKalController.php`, `final`, single-action `__invoke()` —
required by `tests/Arch/ConventionsTest.php`, which enforces final +
single-action invokable controllers.

Two wiring changes are needed and neither is optional:

- `config/routes.yaml` currently registers exactly one resource,
  `../src/Controller/` with namespace `App\Controller`. A controller under
  `src/Kal/Ui/` is **not scanned** and its route will not exist. Add a second
  resource entry for the context's `Ui/` directory.
- `config/services.yaml` tags only `App\Controller\` with
  `controller.service_arguments`. `HealthController` does not extend
  `AbstractController`, so autoconfiguration's `instanceof` rules do not cover
  this project's controller style — the new directory needs the same explicit
  tag, or constructor injection of the command bus will not resolve.

OpenAPI documentation is **out of scope here**: CLAUDE.md plans
NelmioApiDocBundle, which is not installed. The contract below is the source of
truth until it is.

### 5.2 Request body

Field names mirror `CreateKalCommand`'s constructor, so the controller's job is
deserialization + validation, with no renaming.

| Field | Type | Required | Notes |
|---|---|---|---|
| `name` | string | yes | non-blank; DB enforces `trim(name) <> ''` |
| `startsOn` | string | yes | must parse under `DateTime`'s expected format |
| `locales` | string[] | yes | ISO 639-1; **must be non-empty** — `Locales::create()` with no argument throws `kal_no_locales_enabled` |
| `description` | string \| null | no | non-blank when present |
| `endsOn` | string \| null | no | must be `> startsOn` (`kal_invalid_date_range`, also a DB check constraint) |
| `coverPath` | string \| null | no | a **Storage path**, not a URL (see §5.6) |
| `files` | object[] | no | `{fileName, filePath, fileSize, fileExtension, locale, uploadId, uploadedAt}`; each `locale` must be in `locales` |
| `clues` | object[] | no | `{name, startsOn, endsOn, description?, file{…}}`; dates must fall inside the Kal range |
| `meetings` | object[] | no | `{scheduledAt, url, title, timezone?}`; `url` must be `https://`, `timezone` defaults to `Europe/Madrid` |

`organizerId` is deliberately **absent** — see §5.3.

Validation approach: neither `symfony/validator` nor `symfony/serializer` is
installed. Two options, and this is a **DECISION NEEDED**: add
`symfony/validator` and validate a typed request DTO, or hand-roll the shape
check in the controller. Recommendation: **hand-roll for KAL-002.** The domain
already rejects every invalid value with a specific exception (§5.5), so a
validator layer would mostly duplicate invariants that already exist; the
controller only needs to prove the JSON is the right *shape* (required keys
present, arrays are arrays) before constructing the command.

### 5.3 Identity — `organizerId` comes from the JWT, never from the body

**DECIDED (architect):** `organizerId` is derived from the caller's token. It is
**not** a request field. Rationale below.

`CreateKalCommand` takes
`organizerId` as a plain client-supplied string. If the endpoint reads it from
the body, any authenticated user can create a KAL owned by **someone else** —
the FK to `profiles(id)` proves the id exists, not that it is the caller's. The
role model in CLAUDE.md derives "organizer" from `kals.organizer_id`, so writing
that column from untrusted input hands out the organizer role.

The endpoint must therefore derive `organizerId` from the **verified JWT
subject**, mapped `profiles.external_id` → internal ULID `profiles.id` (never
`auth.uid()` directly, per CLAUDE.md's transversal rules). But JWT-via-JWKS
verification is explicitly undecided and unimplemented.

Mechanism: a one-method port in `Kal/Application` (or `Shared`) —
`CurrentUserProviderInterface::organizerId(): UlidValue` — with the JWT-backed
implementation deferred to the auth task. The e2e tier swaps it in
`services_test.yaml` exactly like the repository (§4.2), so **this suite never
needs a real token**, and `organizerId` stays out of the public contract from day
one, which is the part that is expensive to change later.

Consequence for §6: until the JWT implementation lands, e2e proves the controller
takes identity from the port rather than the body (a payload carrying
`organizerId` must not influence the created KAL), but it cannot prove the
token→`profiles.external_id`→ULID mapping. Those assertions belong to the auth
task's own tests.

### 5.4 Success response — identifiers are minted at the edge

**DECIDED (architect):** option (a) below — the identifiers are generated before
the command is dispatched, not inside `Kal::create()`. See §5.4.1 for the
remaining sub-decision (which edge: client or controller).

`Kal::create()` generates both identifiers *inside* the
domain — `UlidValue::generate()` at `src/Kal/Domain/Kal.php:59` and
`InviteToken::generate()` at line 69 — and both `CreateKalHandler::__invoke()`
and `KalRepositoryInterface::create()` return `void`. **The controller therefore
cannot know the id or the invite token of the KAL it just created**, so as
written the endpoint can return neither a body identifying the resource nor a
`Location` header.

That matters beyond tidiness: MVP feature 2 is joining via an invite link, so
the organizer's client needs the invite token immediately after creation.

Options:

| Option | Cost |
|---|---|
| **(a) Generate both at the edge**: controller mints the ULID and invite token, passes them in the command, `Kal::create()` accepts them instead of self-generating | Touches the aggregate's signature and `KalMother`; keeps commands `void`; controller knows both values up front |
| (b) Let the handler return the created id | Breaks the void-command convention the four-bus kernel is built on |
| (c) `202 Accepted`, client re-queries by invite token | Needs a read flow that KAL-002 does not have (create-only), and an extra round trip on the mobile-critical path |

(a) is the only option that keeps CQRS intact and gives the client what it needs
in one request. It also aligns with CLAUDE.md's rule that ULIDs are generated in
the application, not the database.

Contract:

```
201 Created
Location: /kals/{id}
{ "id": "<26-char ULID>", "inviteToken": "<token>" }
```

Implementation shape: `CreateKalCommand` gains `kalId` and `inviteToken` as
plain strings (the command stays all-primitives, so it remains serializable if it
ever moves onto a transport), and `Kal::create()` accepts both instead of calling
`UlidValue::generate()` / `InviteToken::generate()` itself. `KalMother` needs the
same two parameters, defaulted, so existing domain tests keep reading as before.

#### 5.4.1 Which edge — client or controller?

Both were on the table. They differ in one way that matters:

- **Controller-minted** — the server calls `UlidValue::generate()` when building
  the command. No client input to validate, no collision path, nothing new in the
  contract.
- **Client-minted** — the client sends `id` in the body. This buys **retry
  idempotency**: a mobile client that loses the response to a flaky connection can
  safely repeat the request, and the second one conflicts instead of creating a
  duplicate KAL. That is worth something given the MVP targets sign-up from a
  phone. The cost: `id` becomes untrusted input, so the endpoint must validate it
  is a real 26-char ULID and must define a `409 Conflict` path for a duplicate
  primary key — which today would surface as `kal_persistence_failed` (a 500),
  since `DbalKalRepository` wraps any driver failure in
  `KalException::persistenceFailed()`.

Recommendation: **controller-minted for KAL-002.** The idempotency win is real
but it is not free — it adds a validation rule, a new status code, and a
distinguishable unique-violation path through the repository, for a retry
scenario that has not yet been observed. CLAUDE.md's standing instruction for
this project is the simple solution over the elegant one. If retries do turn out
to hurt, the cleaner fix is an `Idempotency-Key` header, which does not require
making the primary key client-controlled.

**Non-negotiable either way: `inviteToken` is always server-generated.** It is a
capability — whoever holds it can join the KAL (MVP feature 2) — so it must come
from `InviteToken::generate()` with its own randomness, never from the request
body, even if the id someday does. A client-chosen invite token would be
guessable, and `kals.invite_token` has a unique index precisely because it is
the lookup key for joining.

### 5.5 Error responses — depends on a separate `ApiResponse` branch

**DECIDED (architect):** the response envelope and the error-code normalization
are **their own branch**, landing before the endpoint task. This spec does not
cover building them; it records what the endpoint will consume and what this
suite may therefore assert.

CLAUDE.md is unambiguous: errors are **codes** (`kal_not_found`), never human
sentences, with translation living in the frontend. The current code only
half-honours that:

| Exception family | State |
|---|---|
| `KalException` | ✅ all codes (`kal_invalid_date_range`, `kal_clue_outside_range`, `kal_no_locales_enabled`, `kal_file_locale_not_enabled`, `kal_meeting_invalid_timezone`, `kal_persistence_failed`, …) |
| `InvalidArgumentException` | ⚠️ **mixed** — `file_size_not_positive`, `invalid_url`, `url_must_be_https` are codes; `The value cannot be negative`, `The value cannot be empty`, `The date time does not match the expected format "%s"`, `The value "%s" is not a valid locale` and others are prose |
| `KalFileException` | ❌ **all prose** — `Invalid file type: %s`, `The file has already been deleted.`, … |

Normalizing `InvalidArgumentException` and `KalFileException` to codes is a
**hard prerequisite** of this endpoint: `getMessage()` is what the error mapper
puts in the response, so shipping as-is leaks English sentences into the API and
forces the frontend to string-match. It is out of KAL-002's scope by decision —
it belongs to the `ApiResponse` branch above — but the endpoint task must not
start before it merges, or the mapping below cannot be implemented as specified.

Ordering, therefore: `ApiResponse` branch → endpoint task → e2e tier. The
acceptance tier (§4.2 steps 1–4) has no such dependency and can land first.

Mapping, once codes exist:

| Status | When | Body |
|---|---|---|
| `400` | body is not valid JSON, or a required key is missing / wrong type | `{"error": "invalid_request"}` |
| `401` | missing or invalid JWT (once §5.3's auth lands) | `{"error": "unauthorized"}` |
| `422` | domain rejected the payload — every `KalException`, `KalFileException` and `InvalidArgumentException` from `Kal::create()` and the VO constructors | `{"error": "<code>"}` |
| `500` | `kal_persistence_failed` | `{"error": "kal_persistence_failed"}` |

Note the 422-vs-500 split: the guards in `Kal::create()` are **client** errors,
so `kal_invalid_date_range` must not surface as a 500. `kal_persistence_failed`
is the only member of `KalException` that is genuinely server-side.

### 5.6 `coverPath` is a path, not a URL

The request field is a Storage path (`{kal_id}/portada.webp`), matching
`kals.cover_path text` in `20260725134400_kal_mvp_schema.sql` and CLAUDE.md's
rule that Postgres stores paths while binaries live in Storage. At the HTTP
boundary it is a JSON string either way, so this contract holds regardless of
how the aggregate types the field internally — which is currently **broken** and
tracked in §7: `Kal.php` types the property `?Url` against an accidental
`phpDocumentor\Reflection\DocBlock\Tags\Reference\Url` import while
`Kal::create()` passes `?string`, and two tests fail with a `TypeError`. Whatever
that fix settles on, the wire format does not change.

## 6. Scenarios per tier

### 6.1 Functional tier — `tests/Kal/Application/.../CreateKalHandlerTest.php`

This tier is in better shape than §1 implies: 15 tests already exist and cover the
happy path, every optional collection, meeting timezone arithmetic, locale
deduplication and four failure paths. It is kept as the functional tier and
extended, not rewritten.

**Already covered — do not duplicate:** minimum required fields; all optional
scalars; meetings (multiple, with and without explicit timezone); `scheduledAt`
parsed in the meeting's timezone and normalized to UTC; default timezone
`Europe/Madrid`; case-insensitive locale deduplication; Kal-level files; clues;
`kal_invalid_date_range`; `kal_no_locales_enabled`; invalid organizer ULID;
`kal_file_locale_not_enabled` (Kal-level files); and "nothing is persisted when
domain validation fails".

**Gaps to close (one test each).** Every row is a guard or VO that exists in the
code today and that no test reaches:

| Scenario | Expected | Why it matters |
|---|---|---|
| Clue dates outside the Kal range | `kal_clue_outside_range` | `guardCluesWithinRange()` is the aggregate's headline invariant per CLAUDE.md and is completely untested |
| Clue **file** locale not in `locales` | `kal_file_locale_not_enabled` | `guardCluesFileLocaleEnabled()` is a separate guard from the Kal-level one that *is* tested |
| Meeting with a bogus timezone | `kal_meeting_invalid_timezone` | only the happy timezone paths are covered |
| Meeting URL with `http://` | `url_must_be_https` | CLAUDE.md forbids non-HTTPS meeting URLs; `HttpsUrl` enforces it, nothing tests it via the handler |
| Meeting URL that is not a URL | `invalid_url` | same seam |
| Blank `name` | `emptyValue` | `NonEmptyStringValue` guards it; DB has a matching `trim(name) <> ''` check |
| `startsOn` in a wrong format | `invalidDateTimeFormat` | the whole payload is strings from HTTP, so format rejection is the most likely real-world failure |
| Unknown `fileExtension` | `KalFileException` (invalid file type) | `FileExtension::tryFromStatus()` is called on raw client input |
| `fileSize` of `0` / negative | `file_size_not_positive` | `FileSizeTest` covers the VO; the handler seam is untested |
| `endsOn` **equal to** `startsOn` | pin the behaviour | the DB constraint is strictly `ends_on > starts_on`; the domain guard's boundary is unverified, so domain and schema could disagree |
| Clue range when Kal `endsOn` is `null` | pin the behaviour | open-ended KALs are allowed; what "within range" means then is unspecified |
| Repository throws | `kal_persistence_failed` propagates, nothing swallowed | **blocked** on the failure-injection hook from §4.3 |

**One existing test to fix, not extend.** `it('creates exactly one debate room
when persisting')` asserts `$repository->debateRoomsCreated() === 1`, but that
counter is incremented by `InMemoryKalRepository::create()` itself — creating
debate rooms is `DbalKalRepository`'s job, which is outside this suite (§3). The
test therefore proves only that `create()` was called once, which the first test
already asserts. It reads as debate-room coverage while providing none: either
rename it to what it measures or delete it, and record real debate-room coverage
as belonging to the deferred SQL task (§11). Fake confidence is worse than a
known gap.

### 6.2 Acceptance tier — new file under `tests/Kal/Application/`

Entry point is `CommandBusInterface::dispatch()` against a booted test kernel
(§4.2). Purpose is wiring, so the matrix stays small — the payload permutations
belong to 6.1.

| Scenario | Assertion |
|---|---|
| Dispatch a valid `CreateKalCommand` | the in-memory repository (pulled from the container) received exactly one `Kal`, built from the command's values |
| Handler discovery | dispatching does not raise `NoHandlerForMessageException` — proves `#[AsMessageHandler(bus: 'command.bus')]` resolved |
| Bus binding by name | the injected `$commandBus` is the `command.bus` adapter, not another bus — `config/services.yaml` binds all four **by parameter name**, so a typo silently yields the wrong bus |
| Middleware pass-through | dispatching succeeds with `MessageStoreMiddleware` in the chain, and nothing is persisted to a message store — `CreateKalCommand` is a plain `CommandInterface` (§4.4) |
| Domain failure through the bus | a `KalException` thrown by the handler arrives at the caller **unwrapped**, not as `HandlerFailedException` |

That last row is the one with a trap behind it. Symfony Messenger wraps any
handler exception in `HandlerFailedException`; `SymfonyCommandBus::dispatch()`
undoes that with `$exception->getPrevious() ?: $exception`. So the unwrapping is a
property of **the adapter**, not of the bus: a test (or a controller) that
dispatches through the raw `MessageBusInterface` instead of
`CommandBusInterface` sees the wrapper and the error mapping in §5.5 breaks.
Both the acceptance tests and the controller must go through
`CommandBusInterface`, and this scenario is what keeps that honest.

### 6.3 E2E tier — new file under `tests/Kal/Ui/`

**Blocked** until the endpoint exists (§5) and the `ApiResponse` branch merges
(§5.5). A thin slice only — everything here is about the HTTP contract, not about
domain permutations.

| Scenario | Assertion |
|---|---|
| Valid `POST /kals` | `201`; body carries `id` (26-char ULID) and a non-empty `inviteToken`; `Location: /kals/{id}` matches the body's id |
| Route is registered | the request does not `404` — guards the `config/routes.yaml` gap in §5.1, which is otherwise only discovered by hand |
| Aggregate actually reached the write side | the in-memory repository received one `Kal` whose `name` matches the request body |
| Identity ignores the body | a payload containing `organizerId` produces a KAL owned by the **port's** organizer, not the body's (§5.3) |
| Malformed JSON | `400` with `{"error": "invalid_request"}` |
| Missing required key (`name`) | `400`, and nothing reached the repository |
| Domain rejection (`endsOn` before `startsOn`) | `422` with `{"error": "kal_invalid_date_range"}` — proves the 422-not-500 split |
| `inviteToken` is server-generated | two identical requests yield two different invite tokens, and a token supplied in the body is ignored (§5.4.1) |

Deferred to the auth task, not written here: token→`profiles.external_id`→ULID
mapping, and `401` on a missing or invalid JWT.

### 6.4 Deliberately not covered by any tier

Stated so the gaps are decisions, not oversights: the six inserts, transaction
and rollback in `DbalKalRepository`; debate-room creation; RLS policies (§2);
read, update and soft-delete flows (KAL-002 is create-only); and `409` on a
duplicate id, which only becomes reachable if §5.4.1 is ever revisited in favour
of client-minted ids.

## 7. Coverage-gap audit of KAL-002 code

A checklist, not a vibe. Everything below was verified against the branch.

### 7.1 Status by layer

| Layer | State |
|---|---|
| `Kal/Domain` — `Kal`, `Clue`, `Locales`, `Meeting`, `FileSize` | tested |
| `Kal/Domain` — 7 other classes | **no test file** (7.2) |
| `Kal/Application` — `CreateKalHandler` | 15 functional tests; 12 scenario gaps (§6.1) |
| `Kal/Infrastructure` — `DbalKalRepository` | **zero tests**, deliberately out of scope (§3, §6.4) |
| `Kal/Ui` | does not exist (§5) |
| `Shared/Domain/ValueObject` — `DateTime`, `Locale`, `Url`, `HttpsUrl` | tested (`HttpsUrl` inside `UrlTest`) |
| `Shared/Domain/ValueObject` — `UlidValue`, `NonEmptyStringValue` | **no test file**, and both are used everywhere (7.2) |
| `Shared/Domain/ValueObject` — `PositiveIntegerValue`, `BooleanValue` | **unused dead code** (7.3) |
| `tests/Arch` | real guardrails exist for `App\Kal\*`; one gap for `Ui` (7.5) |

Note for whoever reads CLAUDE.md next: its claim that the arch tests "pass
vacuously until real domain code exists" is now **out of date**. Both
`LayerBoundariesTest` and `ConventionsTest` carry live `App\Kal\Domain` /
`App\Kal\Application` / `App\Kal\Infrastructure` rules alongside the original
vacuous `App\Domain` templates.

### 7.2 Untested classes, priority-ordered

Priority is by blast radius on untrusted input, not by alphabet:

1. **`InviteToken`** — the highest-value gap. It is a *capability*: whoever holds
   the token joins the KAL (MVP feature 2). `generate()` uses
   `bin2hex(random_bytes(16))` → 32 hex chars. Cover: length and charset,
   two `generate()` calls never collide, `fromString('')` throws
   `kal_empty_invite_token`, `equals()`. Do **not** try to cover the
   `\Random\RandomException` branch — `random_bytes` cannot be made to fail from
   a test, and `kal_invite_token_generation_failed` is unreachable by design.
2. **`UlidValue`** — every id in the system, and §5's boundary validates raw
   client strings through it. Cover `invalidUlid` and `invalidTimestamp`.
3. **`FileExtension`, `FileUploadStatus`** — enums fed directly from client
   payloads (`FileExtension::tryFromStatus($data['fileExtension'])` in the
   handler). Cover the unknown-value path, which is the one a real client hits.
4. **`NonEmptyStringValue`** — guards `name`, `description`, titles, file names.
   Cover blank, whitespace-only, and the `emptyValue` code.
5. **`Files`, `Clues`, `Meetings`** — collection VOs. Cover empty construction,
   preserved ordering, and whatever equality/dedup each one actually implements.
6. **`File`** — composite VO; largely exercised through `CreateKalHandlerTest`,
   so lowest priority of the seven.

`KalRepositoryInterface` is an interface and is not a test target.

### 7.3 Dead code to delete, not to test

`PositiveIntegerValue` and `BooleanValue` have **zero references anywhere in
`src/`** — leftovers from the retired challenge-boilerplate era. They should be
deleted, not covered. Writing tests for them would manufacture coverage for code
the product does not use. (If either is wanted for a future context, it can come
back with its first real caller.)

### 7.4 Domain/schema disagreements — a class of bug worth its own list

The domain and the SQL schema encode overlapping rules, and nothing currently
proves they agree. Two concrete instances found while writing §5 and §6:

| Rule | Domain | `20260725134400_kal_mvp_schema.sql` | Risk |
|---|---|---|---|
| Kal date range | `guardAgainstInvalidDateRange()`, boundary unverified | `check (ends_on is null or ends_on > starts_on)` — strictly greater | if the domain allows `endsOn == startsOn`, the aggregate is valid in memory and rejected by Postgres, surfacing as `kal_persistence_failed` (a 500) instead of a 422 |
| Invite token | `fromString()` rejects only `''` | `check (trim(invite_token) <> '')` | a whitespace-only token passes the domain and fails the DB — same 500-instead-of-422 shape |

Both are cheap to close at the domain level (tighten the guard, then pin it with
a test). They matter more than they look: because this suite has no database
tier, a domain rule that is *looser* than the schema is invisible until
production. Whenever §6.1 says "pin the behaviour", this is why.

### 7.5 Non-test work items this suite depends on

Ordered by what blocks what:

| Item | Blocks | Owner |
|---|---|---|
| Normalize `InvalidArgumentException` + `KalFileException` to error codes | §5.5 error mapping, the whole e2e tier | **`ApiResponse` branch** (architect's decision — outside KAL-002) |
| 6 PHPStan errors in `Locales.php::deduplicate()` (lines 54, 60, 61, 66 — `mixed` method calls, unspecified iterable types, `array` vs `list` return) | `make qa` is red on this branch, so no work here can be declared done | KAL-002 |
| `InMemoryKalRepository`: add failure injection; rename or drop `debateRoomsCreated()` | the `kal_persistence_failed` scenario (§6.1) and the fake-confidence test | KAL-002 (§4.3) |
| `config/routes.yaml` + `config/services.yaml` entries for the context's `Ui/` | the endpoint task and every e2e scenario | endpoint task (§5.1) |
| Extend `ConventionsTest` to cover `App\Kal\Ui` | nothing yet — but the moment §5's controller lands it is unguarded | endpoint task |

That last row is easy to miss: `ConventionsTest` enforces "final + single-action
invokable" against `App\Controller` only. A controller created at
`src/Kal/Ui/Http/` per §5.1 lands **outside** every existing arch rule, so the
convention the spec relies on would go unenforced from its first day.

### 7.6 Closed while writing this spec

- `Kal::coverPath` was typed `?Url` against an accidental
  `phpDocumentor\Reflection\DocBlock\Tags\Reference\Url` import while
  `Kal::create()` passed `?string`, failing 2 tests with a `TypeError`. Fixed by
  dropping the import and typing the property `?string` — consistent with the
  existing `coverPath(): ?string` getter, with `cover_path text` in the schema,
  and with CLAUDE.md's rule that Postgres stores Storage *paths*, not URLs.
  Full suite: 123 passed. **This was a live bug, not a test gap** — worth noting
  that the domain tier's "well covered" reputation did not prevent it.

## 8. Acceptance criteria (definition of done)

Each criterion states how it is **verified**, so "done" is an observation, not an
opinion. The suite ships in three increments; each is independently mergeable.

### 8.1 Increment A — harness + functional tier (no dependencies)

- [ ] `phpunit.xml.dist` exists, sets `APP_ENV=test` and `KERNEL_CLASS=App\Kernel`,
      and declares the four suites of §4.2.
      *Verify:* each suite runs alone and reports a non-zero test count.
- [ ] `.env.test` is committed, contains no secrets, and points `DATABASE_URL` at
      an unreachable value.
      *Verify:* `git ls-files .env.test` lists it; the value is not the project's
      Supabase host.
- [ ] `tests/bootstrap.php` aborts when `APP_ENV !== 'test'`.
      *Verify:* running the suite with `APP_ENV=dev` fails with that message
      instead of executing tests.
- [ ] `InMemoryKalRepository` supports failure injection; `debateRoomsCreated()`
      is renamed to what it measures or removed (§4.3).
- [ ] `CreateKalCommandMother` exists and every acceptance/e2e test builds its
      payload through it (§4.3).
- [ ] The 12 gap scenarios of §6.1 exist and pass, **including**
      `kal_clue_outside_range` and the two boundary cases of §7.4.
      *Verify:* `kal_clue_outside_range`, `kal_meeting_invalid_timezone`,
      `url_must_be_https` and `emptyValue` each appear in exactly one test's
      expectation.
- [ ] `it('creates exactly one debate room when persisting')` no longer asserts
      on a counter the double fabricates (§6.1).
- [ ] Test files exist for the six priority classes of §7.2 — `InviteToken`,
      `UlidValue`, `FileExtension`, `FileUploadStatus`, `NonEmptyStringValue`,
      and the three collection VOs.
- [ ] `make qa` is **green**, which requires the 6 PHPStan errors in
      `Locales.php` to be fixed first (§7.5).
      *Verify:* `make qa` exits 0. This is the gate CLAUDE.md names, and it is
      red on the branch today.

### 8.2 Increment B — acceptance tier (depends on A)

- [ ] `framework: { test: true }` and `config/services_test.yaml` exist, and
      `KalRepositoryInterface` resolves to `InMemoryKalRepository` with a public
      alias.
      *Verify:* a test pulls the double out of the container and reads what was
      persisted.
- [ ] The 5 scenarios of §6.2 pass, including the unwrapped-exception one.
      *Verify:* the domain-failure test asserts `KalException` directly and would
      fail if the dispatch went through the raw `MessageBusInterface`.
- [ ] No test in this tier opens a database connection.
      *Verify:* the tier passes with `DATABASE_URL` unreachable (which §8.1
      already guarantees) — a connection attempt fails loudly rather than
      silently succeeding.

### 8.3 Increment C — e2e tier (depends on B, the `ApiResponse` branch, and the endpoint task)

- [ ] The 8 scenarios of §6.3 pass.
- [ ] The response contract of §5.4 holds exactly: `201`, `id`, `inviteToken`,
      and a `Location` header consistent with the body.
- [ ] A payload containing `organizerId` does **not** influence the created KAL
      (§5.3).
- [ ] Error bodies carry **codes only** — no English sentences anywhere in a 4xx
      or 5xx response.
      *Verify:* grep the tier's expected bodies for spaces inside `error` values;
      there should be none.
- [ ] `kal_invalid_date_range` returns **422**, not 500 (§5.5).

### 8.4 Cross-cutting criteria

- [ ] **Runtime:** suites A + B complete in a couple of seconds on a laptop.
      They are fully in-memory (§3), so anything slower means something is
      reaching out that shouldn't.
      *Verify:* Pest's reported duration; today's 123 tests run in ~0.5s, so this
      is a low bar by construction, and breaking it is a signal.
- [ ] **No fake confidence:** no test asserts on a value that only the test
      double produces. This is the rule the debate-room test broke; it is listed
      as a criterion so the next double gets the same scrutiny.
- [ ] **Tier is implied by location:** a reader can tell a test's tier from its
      directory, with no per-file base-class boilerplate (§4.2, step 6).
- [ ] **The deferred SQL work is written down.** `DbalKalRepository` remains
      untested by decision (§3), so "done" for this suite requires that the gap
      is recorded somewhere durable — not that it is closed.

### 8.5 Explicitly NOT required for done

Restated because a reviewer will ask: `DbalKalRepository` coverage, debate-room
creation, RLS policies, the token→`profiles`→ULID mapping, `401` behaviour, read
/update/soft-delete flows, and `409` on duplicate ids. Each is either another
task's scope (§7.5) or out of scope entirely (§6.4).

## 9. Constraints

Non-negotiable conditions the implementation works inside. Most come from the
repo, not from this spec.

- **No database, no network.** Every tier is in-memory (§3). A test that needs
  either is, by definition, not part of this suite.
- **Everything runs in Docker.** There is no local PHP, so every command goes
  through `make` / `docker compose run`. New Makefile targets may be added, but
  the existing ones (`make test`, `make qa`, `make run-tests-filter`) must keep
  working unchanged.
- **PHPStan at `level: max` with `missingCheckedExceptionInThrows`.** This bites
  test code too: any Mother or double whose call chain can throw a custom
  exception needs `@throws`, checked **recursively**. `CreateKalCommandMother`
  will sit on top of constructors that throw, and the failure-injection hook on
  `InMemoryKalRepository` throws `KalException` by design — both must be
  annotated or `make qa` fails.
- **CS Fixer**: `@PER-CS` + `@Symfony`, `declare(strict_types=1)` everywhere,
  snake_case Pest/PHPUnit method names.
- **`Tests\` is `autoload-dev` only.** Test classes are invisible to the
  container unless declared explicitly (§4.2, step 4). This is why the double
  cannot be swapped by autowiring alone.
- **Commands return `void`.** There is no return value to assert on, so every
  acceptance/e2e assertion about *what was written* goes through the double
  pulled from the container.
- **No CI.** The only gate is a human running `make qa` locally. Every criterion
  in §8 must therefore be checkable by one command, not by a dashboard.
- **Language split**: code and test names in English, comments in Catalan.

## 10. Out of scope

Consolidated from §2, §6.4 and §7.5 so a reviewer has one place to look:

| Not covered here | Where it belongs |
|---|---|
| `DbalKalRepository` — six inserts, transaction, rollback | deferred SQL task (§11) |
| Debate-room creation | same deferred task |
| RLS policies | Supabase-side (pgTAP), frontend's direct-access concern |
| Error-code normalization + response envelope | **`ApiResponse` branch** |
| Building the `POST /kals` controller | endpoint task (this spec fixes only its contract) |
| JWT/JWKS verification, `401`, token→`profiles`→ULID | auth task |
| Read, update, soft-delete flows | later tasks — KAL-002 is create-only |
| `409` on duplicate id | only reachable if §5.4.1 is revisited |
| Deleting `PositiveIntegerValue` / `BooleanValue` | branch hygiene, not test work (§7.3) |
| The 6 PHPStan errors in `Locales.php` | KAL-002, but a fix, not a test (§7.5) |

## 11. Trade-offs

Each of these is a decision with a cost that was accepted knowingly.

**The suite has no database tier.** *Cost:* `DbalKalRepository` ships unverified —
six inserts, an explicit transaction and a rollback path that have never run
under test. The historical `created_at`-null bug class from CLAUDE.md is exactly
what a DB tier would catch. *Why accepted:* speed and zero Supabase dependency,
which keeps the gate a single fast command for a half-time solo project.
*Partial mitigation:* `KalRepositoryInterface::create()` returns `void`, so the
specific "returns the in-memory object instead of the persisted row" shape of
that bug is not expressible through this port. *Required counterweight:* a
separate task for the SQL path must exist and be written down (§8.4) — otherwise
this trade-off quietly becomes an oversight.

**Three tiers instead of two.** *Cost:* the acceptance tier boots a kernel to
test wiring, which is slower than a plain unit test and duplicates the happy path
already covered functionally. *Why accepted:* the four buses are bound **by
parameter name** in `config/services.yaml`, and a misbinding is invisible
functionally and expensive to diagnose at the e2e tier. One cheap tier buys that
signal.

**Controller-minted identifiers.** *Cost:* no retry idempotency — a mobile client
that loses the response can create a duplicate KAL. *Why accepted:* client-minted
ids would add ULID validation, a `409` path and a distinguishable unique-violation
route through the repository, for a failure not yet observed. *Escape hatch:* an
`Idempotency-Key` header later, which does not make the primary key
client-controlled.

**Hand-rolled request validation.** *Cost:* shape-checking code in the controller
that `symfony/validator` would express declaratively. *Why accepted:* the domain
already rejects every invalid value with a specific exception, so a validator
layer would mostly restate invariants that exist — and it is one less dependency.

**Keeping the existing 15 functional tests as-is.** *Cost:* they were written
before this spec, so their style is not uniform with what §6.1 adds. *Why
accepted:* they are good tests covering real behaviour; rewriting them would burn
the increment's budget for no coverage gain.

## 12. Risks & assumptions

### Risks

| Risk | Likelihood | Mitigation |
|---|---|---|
| A domain rule **looser** than the schema surfaces as a 500 in production instead of a 422 — the two instances in §7.4 are already real | high (two found in one pass) | tighten the domain guard and pin it with a test; without a DB tier this class of bug is invisible until deploy |
| A future `Storable` command or query handler reaches DBAL from a test run, and `.env`'s `APP_ENV=dev` + production `DATABASE_URL` means it talks to **real Supabase** | medium | the two rails of §4.4; treat "the CreateKal path opens no connection" as a property to protect, never to assume |
| Increment C stalls because it depends on two other tasks (`ApiResponse`, endpoint) | medium | A and B are independently mergeable by design — do not let C's blockers hold them |
| The controller lands in `App\Kal\Ui`, outside every arch rule, so "final + single-action invokable" goes unenforced from day one | high if unaddressed | extend `ConventionsTest` as part of the endpoint task (§7.5) |
| The in-memory double drifts from the real repository as the port grows (read flows will add `findById`, etc.) and the divergence is **silent** | medium | when the port grows, the double grows in the same commit; the deferred SQL task is the only real check on fidelity |
| "Trade-off accepted" decays into "forgotten" for the untested SQL path | medium | §8.4 makes an open, written task a condition of done |

### Assumptions

- **Pest 4's `pest()->extends(...)->in(...)` composes cleanly with Symfony's
  `KernelTestCase` / `WebTestCase`.** Unverified — it is the one piece of §4.2
  that could need a different shape (a trait or a base class per directory). If it
  fights back, that is a harness detail, not a change to the tiers.
- **`symfony/browser-kit` is sufficient for the e2e tier.** No
  `symfony/css-selector` and no HTTP client are needed, since responses are JSON.
- **The invite token is the only secret in the response.** If §5.4's body ever
  grows fields, the e2e tier's assertions become a de facto contract test and
  should be treated as one.
- **One developer, no CI, half-time.** Criteria that need continuous enforcement
  will not get it; this is why §8 favours single-command checks.
- **This spec survives.** `docs/` is `.gitignore`d on this branch, so
  `kal-002-test-suite.md` is currently **untracked** while two older specs remain
  tracked. If the spec is meant to be the shared source of truth, that needs
  resolving — otherwise the reasoning here lives on one laptop.

## 13. Open questions

Decisions already taken are recorded inline (§5.3, §5.4, §5.5) and are not
repeated. What remains:

1. **Which edge mints the ULID — controller or client?** (§5.4.1) Both were
   accepted as viable. *Recommendation on record:* controller, for KAL-002.
   Needs a yes/no before the endpoint task starts, because it changes the request
   contract.
2. **Request validation: hand-rolled or `symfony/validator`?** (§5.2)
   *Recommendation:* hand-rolled. Not yet confirmed.
3. **Where do the acceptance and e2e tiers live** — `tests/Kal/Application` +
   `tests/Kal/Ui` (mirroring `src/`), or `tests/Acceptance` + `tests/E2E`
   (mirroring the tiers)? The first keeps context locality; the second makes tier
   membership obvious at the top level. *Recommendation:* mirror `src/`, since
   §4.2's per-directory base-class binding already makes the tier implicit.
4. **For each §7.4 mismatch, which side moves?** Tighten the domain to match the
   schema (`endsOn > startsOn`, non-blank invite token), or relax the schema?
   *Recommendation:* tighten the domain — the DB constraint is the stricter and
   more defensible rule, and a 422 beats a 500.
5. **Do `PositiveIntegerValue` and `BooleanValue` get deleted?** (§7.3) They have
   zero references in `src/`. *Recommendation:* delete. Needs the architect's
   confirmation since CLAUDE.md still lists them as VOs to use.
6. **Should the non-test findings stay in this spec?** §7.3, §7.5 (PHPStan) and
   §7.6 are branch hygiene rather than a test work order. Keep them here, or
   move them to an appendix now.
