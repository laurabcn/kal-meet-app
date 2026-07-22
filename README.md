# PHP challenge boilerplate

A reusable starting point for PHP take-home / technical-challenge exercises:
Dockerized PHP 8.3, Symfony (Console + DI, with an optional HTTP skeleton),
Pest, PHPStan and PHP CS Fixer, driven through a `Makefile`. Everything runs
in Docker — no local PHP installation required.

## What's in the box

- **Docker**: `docker/php/Dockerfile` (PHP 8.3-cli + Composer) and
  `docker-compose.yml` with `app`, `web`, `pest`, `phpstan`, `cs-fixer`
  services.
- **Symfony**: `symfony/console` + `symfony/dependency-injection` always; a
  minimal HTTP skeleton (`symfony/framework-bundle` + `symfony/runtime`) is
  included and wired up with a working `GET /` health-check route, in case
  the challenge is an HTTP API rather than a CLI tool.
- **Testing**: Pest.
- **Static analysis**: PHPStan at `level: max`, with checked-exception
  analysis enabled (`phpstan.dist.neon`).
- **Code style**: PHP CS Fixer, `@PER-CS` + `@Symfony` rulesets
  (`.php-cs-fixer.dist.php`).
- **Makefile**: `make help` lists every target, grouped by category.

## Quick start

```bash
make setup        # build the image, install Composer dependencies
make test          # run Pest
make qa             # PHPStan + CS Fixer (dry-run) + Pest
make bash           # interactive shell in the app container
make serve          # serve the HTTP skeleton at http://localhost:8000
```

## The workflow

This repo ships with a small set of **AI helpers** in `.claude/` (commands,
agents and skills) that you drive from [Claude Code](https://claude.com/claude-code).
They turn one loose idea into a shipped, reviewed change through four steps.
You never have to remember the internals — you run one thing per step.

> The golden rule: **think first, code last.** Each step produces something you
> approve before the next one starts. If you are unsure at any point, stop and
> ask; nothing here is meant to run unattended.

### Step 1 — Give your idea to `/spec`

Your idea **is** the input to this step — there is no separate "write it up
first" stage. In Claude Code, run `/spec` followed by the change in plain words:

```
/spec Add an endpoint that returns the health of the Postgres connection, not just the app.
```

**No special format is needed, and that is on purpose.** You can be as rough or
vague as you like — turning that into something precise is exactly `/spec`'s job.
It will:

1. Ask you short questions (with options to pick from) to remove any ambiguity.
2. Write a spec file to `docs/specs/<name>.md`, section by section, asking you
   to approve each part.

If you already have detail in your head, you can put it in the prompt to save a
few questions (the problem, what "done" looks like, what's out of scope) — but
it is optional. Starting with a single sentence is completely fine.

The result is a clear work order: the problem, how it should behave, what
"done" means, and what is **out of scope**. Read it — this is where you catch
"oh, I hadn't thought of that" while it is still cheap.

**No code is written in this step.**

### Step 2 — Let the agents build it

Once the spec looks right, run:

```
/team-lead Implement the spec in docs/specs/dbal-health-check.md
```

`/team-lead` reads the spec, breaks it into pieces, and hands each piece to a
**principal-engineer** agent that writes the actual code following this repo's
conventions (see [`CLAUDE.md`](./CLAUDE.md)). It works in parallel where it
can, and tells you what it changed.

Review the diff yourself too — the agents are good, not infallible.

### Step 3 — Check it really works

Before trusting anything, run the gate:

```bash
make qa
```

This runs PHPStan (level max), the CS Fixer dry-run, and the full Pest suite.
**Everything must pass.** There is no CI yet, so this local gate is the only
safety net — do not skip it.

### Step 4 — Review and open the PR

For a quick pre-commit self-check, `/code-review` reviews staged changes,
the branch vs its base, or a GitHub PR by number — pick whichever scope fits.

For a deeper look before opening the PR, run:

```
/pr-deep-review
```

This runs three reviewers in parallel — one for **bugs** (correctness), one for
**security** (an attacker's view), and one for **conventions** (does it match
this repo's rules, per `CLAUDE.md`). Fix what they find, re-run `make qa`, then
open the pull request:

```
/github-create-pr
```

It writes a clear PR description from your commits and the spec.

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

Small fixes do not need all four steps — for a one-line change, just make it,
run `make qa`, and open the PR. The full flow is for anything you would
struggle to hold in your head at once.

### If you track work in Notion instead

A parallel set of helpers exists for that: `/notion-create-task` creates a
task, `start-task` picks one up (task → branch + implementation brief),
`bug-fix-from-notion-task` diagnoses and fixes a bug from a task, `validate-pr`
checks a diff against its task/spec, and `pr-description` drafts the PR body
from a task instead of from commits alone. These are independent of the
`/spec` → `/team-lead` loop above — use whichever fits how you're tracking the
work.

## Adapting this for a new challenge

1. **Rename the package and namespace** in `composer.json`:
   `"name": "you/php-challenge"` and `"App\\": "src/"` under `autoload.psr-4`
   — pick whatever fits the domain you're modeling.
2. **If the challenge is CLI-only** (no HTTP API), remove the HTTP-only
   parts so the boilerplate stays honest about what's actually used:
   - `public/`, `config/`, `src/Kernel.php`, `src/Controller/`
   - `symfony/framework-bundle`, `symfony/runtime`, `symfony/dotenv` from
     `composer.json` (keep `symfony/console` + `symfony/dependency-injection`
     if you still want a CLI entrypoint)
   - the `web` service in `docker-compose.yml` and the `serve` target in the
     `Makefile`
   - `.env`
3. **If the challenge needs a database**, add a service to
   `docker-compose.yml` and the matching Doctrine/DBAL packages — this
   boilerplate intentionally ships without one, since not every challenge
   needs persistence.
4. Start writing the actual domain under `src/`, tests under `tests/`.

## Notes

- `phpstan.dist.neon`'s `missingCheckedExceptionInThrows` check means any
  method that throws a custom exception must document it with `@throws`.
  Drop that line if it's more friction than value for a given challenge.
- The HTTP skeleton uses PHP's built-in dev server (`php -S`), not
  `symfony server` or a production-grade setup — it's meant for local
  iteration during a take-home, not deployment.
