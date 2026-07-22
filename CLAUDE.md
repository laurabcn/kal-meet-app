# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A reusable, Dockerized starting point for PHP take-home / technical-challenge exercises: PHP 8.3, Symfony (Console + DI, with an optional HTTP skeleton), Pest, PHPStan and PHP CS Fixer. Everything runs in Docker — there is no local PHP installation, so every command below goes through `make` / `docker compose run`.

## Commands

```bash
make setup              # build the image, install Composer dependencies
make build               # (re)build the app image only
make bash                 # interactive shell in the app container
make serve                # serve the HTTP skeleton at http://localhost:8000

make test                 # run the full Pest suite
make run-arch             # run only the architecture tests (tests/Arch)
make run-tests-filter p='name'   # run Pest filtered by test name
make run-tests-retry      # re-run only tests that failed last time

make qa                   # PHPStan + CS Fixer (dry-run) + Pest — run this before considering work done
make run-phpstan          # PHPStan only
make run-cs-fixer         # CS Fixer, dry-run/diff only (does not modify files)
make run-cs-fixer-fix     # auto-fix code style violations

make composer-require p=vendor/package       # require a runtime package
make composer-require-dev p=vendor/package   # require a dev package
```

Run `make help` for the full, grouped list. There is no `bin/console`; this project only pulls in `symfony/console` + `symfony/dependency-injection` (plus the optional HTTP skeleton), not a CLI application skeleton — add one if a challenge needs it.

## Adapting this boilerplate for a new challenge

- Rename the package/namespace in `composer.json` (`"name"`, `App\\` PSR-4 root) to fit the challenge domain.
- If the challenge is CLI-only, strip the HTTP-only parts: `public/`, `config/` (framework/routes), `src/Kernel.php`, `src/Controller/`, the `web` service in `docker-compose.yml`, the `serve` Makefile target, and the `symfony/framework-bundle` / `symfony/runtime` / `symfony/dotenv` composer requirements.
- This boilerplate ships without a database by default. `doctrine/dbal` + `doctrine/doctrine-bundle` are wired in but intentionally **DBAL-only, no ORM** (`config/packages/doctrine.yaml` has no `orm:` key) — add `doctrine/orm` and entity mapping yourself if a challenge needs it. Connection comes from `DATABASE_URL` in `.env` (see `.env.example`); `pdo_pgsql`/`pgsql` are already installed in `docker/php/Dockerfile` for Postgres-backed challenges (e.g. Supabase).
- `.env` is git-ignored (`.env.example` documents the expected keys) — never commit real secrets into it.
- Start writing the actual domain under `src/`, tests under `tests/`.

## Architecture

### Composition root

`src/Kernel.php` uses `MicroKernelTrait` with no overrides — all wiring is convention-based config in `config/`:
- `config/bundles.php` — registered bundles (`FrameworkBundle`, `DoctrineBundle`).
- `config/services.yaml` — `App\` is autowired/autoconfigured by default. The four message buses (see below) are bound to constructor parameters by **name** (`$commandBus`, `$queryBus`, `$eventBus`, `$externalMessageBus`), not by type — a class that wants a specific bus must name its constructor parameter accordingly.
- `config/packages/messenger.yaml` — declares the four named buses and attaches `MessageStoreMiddleware` to `command.bus`, `event.bus` and `external_message.bus` (not `query.bus`, since queries are never `Storable`).
- `config/packages/doctrine.yaml` — DBAL connection only.

### `src/Shared` — the CQRS/messaging kernel

This is the one substantial piece of domain-agnostic infrastructure the boilerplate ships with; everything else under `src/` is meant to be challenge-specific. It follows DDD/hexagonal layering (`Domain` → `Application` → `Infrastructure`) and is organized around **four independent Symfony Messenger buses**, each with its own adapter in `Shared/Infrastructure/Symfony/Bus/`:

| Bus | Interface | Adapter | Purpose |
|---|---|---|---|
| `command.bus` | `CommandBusInterface` | `SymfonyCommandBus` | in-process writes, exactly one handler |
| `query.bus` | `QueryBusInterface` | `SymfonyQueryBus` | in-process reads, exactly one handler, returns a `ResponseInterface` |
| `event.bus` | `DomainEventBusInterface` | `SymfonyDomainEventBus` | in-process domain events, zero-or-more handlers |
| `external_message.bus` | `ExternalMessageBusInterface` | `SymfonyExternalMessageBus` | cross-bounded-context messages, produced and consumed over a transport |

Key conventions to know before touching this code:

- **Storable vs plain messages**: `StorableCommandInterface`/`StorableEventInterface` (both extend `StorableMessageInterface` → `ExternalMessageInterface`) mark messages that `MessageStoreMiddleware` should persist via `MessageStoreRepositoryInterface`. A command/event only needs to implement one of these if it must be recorded in the message store; plain `CommandInterface`/`DomainEventInterface` implementations pass through untouched.
- **Producing vs consuming**: `MessageStoreMiddleware` tells the two apart by the presence of a `ReceivedStamp` (present when the message came off a transport). On the consuming side the store lookup key is the transport name; on the producing side it's `$message::boundedContext()`. `MessageStoreRepositoryMapper::find()` matches a transport name back to a bounded context by the `{bc-kebab-case}-{suffix}` naming convention.
- **`AggregateRoot`** (`Shared/Domain/Model`) accumulates domain events via `recordEvent()`/`pullDomainEvents()` — the standard base for any aggregate that needs to publish events after a write.
- **Value objects** (`Shared/Domain/ValueObject`) are the primitives (`UlidValue`, `DateTime`, `NonEmptyStringValue`, `PositiveIntegerValue`, `BooleanValue`) domain code should use instead of raw scalars.

### Architecture tests (`tests/Arch`)

Pest arch tests are the enforcement mechanism for the layering rules above, and are templates meant to be extended per bounded context (not just left as-is):
- `LayerBoundariesTest.php` — `Domain` must not depend on `Application`, `Infrastructure`, Symfony or Doctrine; `Application` must not depend on `Infrastructure`; nothing outside `Infrastructure` (except the `Kernel`) may depend on it.
- `ConventionsTest.php` — strict types in `Domain`/`Application`, `Domain` classes final, controllers final + single-action invokable, no debugging leftovers (`dd`, `dump`, `var_dump`, `print_r`) anywhere.

Both files currently check against the bare `App\Domain`/`App\Application`/`App\Infrastructure` namespaces and pass vacuously until real domain code exists — when adding a bounded context (e.g. `App\<Context>\Domain`), extend these checks rather than assuming they already cover it.

### Static analysis and style

- PHPStan runs at `level: max` with `missingCheckedExceptionInThrows` enabled (`phpstan.dist.neon`) — any method throwing a custom exception must document it with `@throws`, checked recursively through call chains. Drop that line if it's more friction than value for a given challenge.
- CS Fixer applies `@PER-CS` + `@Symfony` plus `declare_strict_types`, snake_case PHPUnit/Pest method names, and keeps `throw` as its own statement (`single_line_throw: false`) (`.php-cs-fixer.dist.php`). It scans `config/`, `public/`, `src/`, `tests/`, excluding `reference.php`.
