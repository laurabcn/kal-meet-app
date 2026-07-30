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
- **Testing**: Pest.
- **Static analysis**: PHPStan at `level: max`, with checked-exception analysis
  enabled (`phpstan.dist.neon`).
- **Code style**: PHP CS Fixer, `@PER-CS` + `@Symfony` rulesets
  (`.php-cs-fixer.dist.php`).
- **Docker**: `docker/php/Dockerfile` (PHP 8.5-cli + Composer, with `pdo_pgsql`)
  and `docker-compose.yml` with `app`, `web`, `pest`, `phpstan` and `cs-fixer`
  services. Everything runs in containers — **no local PHP installation**, so every
  command goes through `make`.

The frontend (Vue 3 + Vite + TypeScript) and its Playwright end-to-end tests live in
their own repository. The frontend talks to Supabase directly for auth and photo
uploads, guarded by RLS policies; this backend handles only what cannot live in the
client — invite-token validation, emails and reminders, crons, and signed photo URLs.

## Quick start

```bash
make setup       # build the image, install Composer dependencies
supabase start   # local Postgres + auth + storage (its own containers, via the Supabase CLI)
supabase db push # apply the migrations in supabase/migrations/
make qa          # PHPStan + CS Fixer (dry-run) + Pest
```

`make help` lists every target, grouped by category. The ones you will use most:

```bash
make test        # run Pest
make run-arch    # only the architecture tests (tests/Arch)
make bash        # interactive shell in the app container
make serve       # serve the API at http://localhost:8000
```

Integration tests run against **local** Supabase, never production.

## Layout

Hexagonal + CQRS, organised **per bounded context** rather than per technical layer:

```
src/
  Shared/       the domain-agnostic kernel: four Messenger buses, value objects,
                AggregateRoot, DBAL connection, domain events between contexts
  Kal/          the Kal aggregate — clues, meetings, and the KAL write paths
  Controller/   the health-check endpoint, outside any context
```

`src/User/` (identity — profile only; a person's role is derived per KAL and never
stored) is planned but not written yet.

Each context has the same four layers: `Domain/` (pure PHP — entities, value
objects, invariants, and the repository interface), `Application/` (commands and
queries dispatched through the buses), `Infrastructure/` (DBAL adapters) and `UI/`
(controllers that translate HTTP to messages and never touch SQL).

**A new context must be registered by hand in two files** — `config/routes.yaml`
and `config/services.yaml`. Miss the first and the `#[Route]` attributes are never
read: the endpoint 404s with no visible error.

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

PHPStan (level max), the CS Fixer dry-run, and the full Pest suite.
**Everything must pass.** There is no CI yet, so this local gate is the only safety
net — do not skip it.

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
- Error responses carry **codes**, never human sentences (`kal_not_found`, not
  "KAL not found") — translation lives in the frontend.
- Secrets go in `.env.local` only — never in the Dockerfile, never in Git. The
  files `supabase start` generates under `supabase/.temp/` contain dev credentials
  and are ignored on purpose.
- The `web` service uses PHP's built-in dev server (`php -S`), not a
  production-grade setup — it is for local iteration. Deployment builds from
  `docker/php/Dockerfile`.
