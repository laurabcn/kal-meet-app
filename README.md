# KAL App — backend

The backend of **KAL App**, a web tool for KAL (knit-along) organizers. It replaces
the current patchwork of Instagram + Google Forms + Telegram/Discord + spreadsheets
with a single app: create a KAL and its clues, invite participants, collect progress
photos, schedule meetings, send reminders.

Status: **MVP under construction.** See [`CLAUDE.md`](./CLAUDE.md) for the domain
model, the product scope, and the conventions this codebase follows.

## Stack

- **PHP 8.5 + Symfony 8**, with **Doctrine DBAL and direct SQL** — no ORM.
  Aggregates are reconstructed by hand in the repositories.
- **Supabase** (Postgres + magic-link auth + private buckets) for the database,
  authentication and file storage. Migrations are plain SQL under
  `supabase/migrations/`, versioned in Git, with no dependency on Doctrine
  Migrations.
- **Auth on this API**: Symfony Security verifies the Supabase user JWT via JWKS
  (asymmetric). Deny-by-default; only `GET /` is public. Spec:
  [`docs/specs/supabase-jwt-authentication.md`](./docs/specs/supabase-jwt-authentication.md).
- **Testing**: Pest (`make qa` stays offline; `make test-db` hits local Supabase).
- **Static analysis**: PHPStan at `level: max`, with checked-exception analysis
  enabled (`phpstan.dist.neon`).
- **Code style**: PHP CS Fixer, `@PER-CS` + `@Symfony` rulesets
  (`.php-cs-fixer.dist.php`).
- **Docker**: PHP-FPM (`docker/php/Dockerfile`) + nginx (`docker/nginx/`), plus
  one-shot services for Pest / PHPStan / CS Fixer. Everything runs in containers —
  **no local PHP installation**, so every command goes through `make`. A root
  `Dockerfile` is aimed at deploy (Fly/Railway). CI mirrors `make qa` in
  `.github/workflows/ci.yml`.

The frontend (Vue 3 + Vite + TypeScript) and its Playwright end-to-end tests live in
their own repository. The frontend talks to Supabase directly for auth and photo
uploads, guarded by RLS policies; this backend handles only what cannot live in the
client — JWT-gated write APIs, invite-token validation, emails and reminders, crons,
and signed photo URLs.

## Quick start

```bash
cp .env.example .env   # then fill Supabase / secrets as needed (.env.local for secrets)
make setup             # build the image, install Composer dependencies
supabase start         # local Postgres + auth + storage (Supabase CLI containers)
supabase db push       # apply supabase/migrations/
make up                # PHP-FPM + nginx → http://localhost:8080
make qa                # PHPStan + CS Fixer (dry-run) + Pest
make logs              # Tail Monolog JSON (app container stderr)
make logs-errors       # Only WARNING / ERROR / CRITICAL
# Optional Slack alerts for 5xx: set SLACK_DSN in .env.local
#   slack://xoxb-...@default?channel=alertas  (bot invited to #alertas)
```

`make help` lists every target, grouped by category. The ones you will use most:

```bash
make up          # start API (http://localhost:8080; override with HTTP_PORT=…)
make test        # run Pest (no DB)
make test-db     # Pest against local Supabase (needs `supabase start`)
make coverage    # test coverage (needs `supabase start` — see below)
make run-arch    # only the architecture tests (tests/Arch)
make bash        # interactive shell in the app container
```

### Test coverage

```bash
make coverage            # the real number; needs `supabase start`
make coverage-hermetic   # fast, no DB — but see the warning below
```

The HTML report lands in `var/coverage/index.html` (already gitignored). Coverage
runs on PCOV, not Xdebug: this is only about measuring, and PCOV is much faster.
It ships **disabled** (`pcov.enabled=0`) so it never slows down a normal
`make test`; the coverage targets switch it on for their own run.

**`make coverage` runs both test suites and merges the results, and that matters.**
The DBAL repositories are only exercised by the Postgres tests, which live in a
separate config (`phpunit.db.xml.dist`). Measure the hermetic suite alone and you
get a number that is simply wrong:

| | `make coverage-hermetic` | `make coverage` |
|---|---|---|
| `KalRepository` | 0.0% | 98.78% |

So treat `coverage-hermetic` as a quick local signal only, and never as the figure
to report or act on — chasing the 0% would mean writing tests that already exist.

**What is excluded, and when to stop excluding it.** `src/Shared` ships a
messaging kernel built for asynchronous, multi-service work: transport
serializers, an external-message bus, a domain-event bus. The MVP is a
synchronous monolith and never starts any of it — `messenger.yaml` declares no
transports, nothing implements `Storable*`, and `recordEvent()` is called
nowhere. That code is kept (email reminders and the chat will need parts of it),
but while it sleeps it drags the figure down and buries the gaps in code that
does run. Both phpunit configs exclude it, and **every exclusion states the
event that should remove it** — a doctrine transport appearing, a handler
publishing a domain event, an aggregate typing one of the spare value objects.
Keep the two config files in step: `make coverage` merges both runs, so an
exclusion in only one of them has no effect.

`make coverage` is **not** part of `make qa`. It needs `supabase start`, and the
whole point of `qa` is that it stays hermetic and runs with nothing else up. It
sits alongside `test-db`: run it when you want the number, not on every change.

## Manual API smoke (Postman)

Import the collection and local environment from `postman/`:

- `postman/kal-meet-app.postman_collection.json`
- `postman/local.postman_environment.json`

Set `baseUrl` (default `http://localhost:8080`) and an `accessToken`. That token must be a **user** Supabase Auth JWT (`access_token` from a session) — not the `ANON_KEY` / `SERVICE_ROLE_KEY` from `supabase status`.

### Getting an `access_token`

From this repo root (with `supabase start`):

```bash
# defaults: organizer@kal.local / password / signup=1
make access-token

# override when needed
make access-token email='you@example.com' password='your-password'
make access-token signup=0   # login only, no signup attempt
```

Same script as `scripts/fetch-access-token.sh` (also used by the Cursor skill
**`supabase-access-token`**). It reads `API_URL`, `ANON_KEY` and
`SERVICE_ROLE_KEY` from `supabase status`, logs in via
`/auth/v1/token?grant_type=password`, **ensures a `profiles` row for the JWT
`sub`** (needed while local Auth signup has no `handle_new_user` yet / for
users created before that trigger), copies the `access_token` to the clipboard,
and prints it. Paste that value into Postman’s `accessToken` / Bearer field.

If you still get `401 auth_profile_not_found`, apply migrations
(`supabase db reset` or `supabase migration up`) so
`20260803224500_profiles_handle_new_user.sql` is loaded, then run
`make access-token` again.

## Skills

Two layers of agent skills show up in Cursor / Claude Code. Invoke them by name
(e.g. `@reviewing-branch`) or by asking in plain language that matches their
description.

### Personal (any project) — `~/.cursor/skills/`

These live on your machine, not in this repo. Useful across KAL and other work:

| Skill | What it does |
| --- | --- |
| `reviewing-branch` | Structured review of the branch/PR (+ working tree): conventions, Blocker/Major/Minor, auth/infra/deploy. |
| `describing-pr` | English PR body from the open PR or branch vs base; uses `.github/pull_request_template.md` when present; copies to clipboard. |
| `supabase-access-token` | Fetches a Supabase **user** `access_token` (email + password, optional signup); clipboard + stdout. Prefer `make access-token` in this repo (`scripts/fetch-access-token.sh`). |

### Project — `.claude/skills/`

Shipped with this repository. Grouped by use:

**Product & requirements**

| Skill | What it does |
| --- | --- |
| `product-context` | MVP / Fase 2 / Patterns / IA roadmap and business context for prioritisation. |
| `understanding-user-problem` | Clarify an ambiguous request before writing requirements. |
| `defining-requirements` | Turn a clarified problem into an implementation-ready spec. |

**Day-to-day engineering**

| Skill | What it does |
| --- | --- |
| `logging` | How to emit structured PSR-3 logs (static message + context; Monolog → stderr). |
| `creating-migration-files` | Migration lifecycle via Makefile — never hand-write migration files. |
| `fix-flaky-test` | Diagnose and fix flaky Pest tests without masking the flake. |
| `verification-before-completion` | Evidence-first “done / green / ready” gate. |
| `verifying-documentation` | Fact-check AI-written docs against the code. |

**GitHub**

| Skill | What it does |
| --- | --- |
| `github-writing-commits` | Conventional Commits, atomic changes. |
| `github-managing-branches` | Branch naming and dependent features. |
| `github-creating-pull-requests` | Draft PRs, title rules, `gh` usage. |
| `github-creating-pull-request-body` | PR body guidelines. |
| `pr-description` | Fill the PR template (English), optional Notion link, copy to clipboard. |
| `github-fetching-pull-request-review-comments` | Fetch inline review comments. |
| `github-posting-pull-request-review` | Post inline review comments. |
| `github-answering-pull-request-review-comments` | Reply to inline review comments. |
| `reviewing-code` | Full code-review workflow for this repo’s conventions. |
| `triage-pr-comments` | Triage comments left on your PR (Problem / Suggestion / Noise) and act. |
| `validate-pr` | Check the branch against its Notion task and/or `/spec` file. |

**Notion**

| Skill | What it does |
| --- | --- |
| `notion-creating-task` | Create a board task via Notion MCP. |
| `notion-fetching-task` | Fetch and structure a task. |
| `notion-task-body` | How to write task descriptions. |
| `start-task` | Resolve a Notion task → branch + implementation brief. |

## Layout

Hexagonal + CQRS, organised **per bounded context** rather than per technical layer:

```
src/
  Shared/       CQRS buses, value objects, AggregateRoot, DBAL wiring,
                Supabase JWT auth (JWKS, firewall helpers)
  User/         identity — profiles.id (ULID) ↔ external_id (Supabase auth uuid);
                no stored role (role is per-KAL)
  Kal/          the Kal aggregate — clues, meetings, write paths (e.g. POST /kal)
  Controller/   health check (GET /), outside any context
```

Each context has the same four layers: `Domain/` (pure PHP — entities, value
objects, invariants, and the repository interface), `Application/` (commands and
queries dispatched through the buses), `Infrastructure/` (DBAL adapters) and `UI/`
(controllers that translate HTTP to messages and never touch SQL). Controllers use
`#[AsController]` so action arguments resolve correctly.

**Routing:** `config/routes/*.yaml` is loaded automatically (one file per context,
`type: attribute`). **Services:** `config/services.yaml` only `imports`
`config/services/*.yaml` — that directory is **not** auto-loaded; order matters
(`app.yaml` glob first, then overrides such as `security.yaml`). Miss a routes
file and `#[Route]` attributes are never read: the endpoint 404s with no visible
error.

## The workflow

This repo ships with a set of **AI helpers** in `.claude/` (commands, agents and
skills) that you drive from [Claude Code](https://claude.com/claude-code). They turn
one loose idea into a shipped, reviewed change through four steps.

> The golden rule: **think first, code last.** Each step produces something you
> approve before the next one starts. If you are unsure at any point, stop and
> ask; nothing here is meant to run unattended.

### Step 1 — Give your idea to `/spec`

Your idea **is** the input to this step — there is no separate "write it up first"
stage. In Claude Code, run `/spec` followed by the change in plain words:

```
/spec Let an organizer reschedule a clue without touching the rest of the KAL.
```

**No special format is needed, and that is on purpose.** You can be as rough or
vague as you like — turning that into something precise is exactly `/spec`'s job.
It will:

1. Ask you short questions (with options to pick from) to remove any ambiguity.
2. Write a spec file to `docs/specs/<name>.md`, section by section, asking you to
   approve each part.

The result is a clear work order: the problem, how it should behave, what "done"
means, and what is **out of scope**. Read it — this is where you catch "oh, I
hadn't thought of that" while it is still cheap.

**No code is written in this step.**

### Step 2 — Let the agents build it

Once the spec looks right, run:

```
/team-lead Implement the spec in docs/specs/clue-reschedule.md
```

`/team-lead` reads the spec, breaks it into pieces, and hands each piece to a
**principal-engineer** agent that writes the code following this repo's conventions.
Domain modelling decisions go to **kal-domain-modeler**, which knows the aggregate
as it stands and which rules the database enforces versus the ones only PHP does.

Review the diff yourself too — the agents are good, not infallible.

### Step 3 — Check it really works

Before trusting anything, run the gate:

```bash
make qa
```

PHPStan (level max), the CS Fixer dry-run, and the full Pest suite (no network/DB).
CI runs the same checks on push/PR. For persistence against real schema:

```bash
make test-db   # needs `supabase start`
```

**Everything that should pass must pass** before you merge.

### Step 4 — Review and open the PR

For a quick pre-commit self-check, `/code-review` reviews staged changes, the branch
vs its base, or a GitHub PR by number — pick whichever scope fits.

For a deeper look before opening the PR, run:

```
/pr-deep-review
```

This runs three reviewers in parallel — one for **bugs** (correctness), one for
**security** (an attacker's view), and one for **conventions** (does it match this
repo's rules). Fix what they find, re-run `make qa`, then open the pull request:

```
/github-create-pr
```

It writes the PR description from your commits and the spec, and opens it as a draft.

---

### The steps at a glance

```
your idea  ─┐   (a sentence is enough; /spec asks the rest)
            ▼
1. /spec "<your idea>"  →  writes docs/specs/<name>.md   (the plan, approved by you)
2. /team-lead           →  agents write the code from the spec
3. make qa              →  PHPStan + CS Fixer + Pest must pass
4. /pr-deep-review      →  fix findings  →  /github-create-pr
```

Small fixes do not need all four steps — for a one-line change, just make it, run
`make qa`, and open the PR. The full flow is for anything you would struggle to hold
in your head at once.

### If you track work in Notion instead

A parallel set of helpers exists for that: `/notion-create-task` creates a task,
`start-task` picks one up (task → branch + implementation brief),
`bug-fix-from-notion-task` diagnoses and fixes a bug from a task, `validate-pr`
checks a diff against its task/spec, and `pr-description` drafts the PR body from a
task instead of from commits alone. These are independent of the `/spec` →
`/team-lead` loop above — use whichever fits how you're tracking the work.

## Notes

- `phpstan.dist.neon`'s `missingCheckedExceptionInThrows` check means **any method
  that throws a custom exception must document it with `@throws`**, verified
  recursively through the whole call chain.
- Error responses carry **codes**, never human sentences (`kal_not_found`,
  `auth_token_missing`, …) — translation lives in the frontend.
- Secrets go in `.env.local` only — never in the Dockerfile, never in Git. The
  files `supabase start` generates under `supabase/.temp/` contain dev credentials
  and are ignored on purpose.
- Local HTTP is **nginx → PHP-FPM** (`make up`). Deploy image is the root
  `Dockerfile` (FPM); pair it with a reverse proxy in the host platform.
