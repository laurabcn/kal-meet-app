---
name: fix-flaky-test
description: Diagnose and fix a flaky Pest test by root cause — reproduce it, pin the real mechanism, apply the smallest fix, then prove stability by re-running. Never masks the flake. Triggers like "arregla el test flaky X", "este test falla a veces", "fix flaky test", "test intermitente en CI".
argument-hint: "[test path or Class::method, or a CI failure description] [optional pasted CI output]"
allowed-tools: Bash, Read, Grep, Glob, Edit, AskUserQuestion, Skill
---

# fix-flaky-test

Makes an intermittently-failing test **reliable by fixing its root cause** — never by hiding it. A flaky test that blocks the CI gate is the target; the win condition is *the same test, green every time*, with the mechanism understood. The bias is the repo's: smallest correct change, and prove it.

Runs locally against the single shared `app` container (via `docker compose run`) — so reproduction and verification are **sequential loops**, not parallel (parallel runs would collide on any shared database/service state).

## Arguments

- `$1`: the flaky test — a path (`tests/Unit/.../FooTest.php`), a `Class::method`, or a CI-failure description. If only a class/method is given, locate the file with `Grep`/`Glob`.
- `$2` (optional): pasted CI output / failure message to seed the diagnosis.

## Hard rules (do NOT mask the flake)

- **Never** add skip/incomplete annotations, retry wrappers (`make run-tests-retry` is for triage, not a fix), or group exclusions to dodge it.
- **Never** bump a `sleep`/timeout as the *sole* fix, delete or disable the test, broaden a `try/catch` to swallow the failure, or weaken an assertion so it always passes.
- **Never** edit shared test infrastructure (`tests/Pest.php`, bootstrap, shared setup) without strong, stated justification — that ripples to every test.
- Fix the **root cause**: production code if the test correctly exposes a real bug; otherwise the test's isolation/setup.

## Steps

### 1. Locate the test and its area
Resolve `$1` to a file and note its area from the path (`tests/Arch/…` → architecture test, `tests/Unit/…` → unit, `tests/Feature/…` or similar → integration-style — follow whatever structure this repo actually uses, don't assume suite names it doesn't have). Read the test, its setup/teardown, and the production code under test.

### 2. Reproduce — confirm it's actually flaky
Loop the single test in the container and record pass/fail per run:
```bash
for i in $(seq 1 10); do
  docker compose run --rm app vendor/bin/pest <path> --filter '<test_method_name>' -q
  echo "run $i exit=$?"
done
```
If it passes 10/10 **alone**, the flake is almost certainly **ordering / shared-state**: reproduce by running the whole file, then the surrounding directory, so a sibling test's leftover state is in play:
```bash
docker compose run --rm app vendor/bin/pest <path> -q               # whole file
docker compose run --rm app vendor/bin/pest tests/<dir> -q           # neighbours
```
Note the pattern: time-dependent? ordering-dependent? state-dependent? async-dependent? If you can't reproduce after sequential + directory-level runs, say so and proceed to analyze for known patterns, applying a preventive fix only if one is clear.

### 3. Diagnose the mechanism
Match against the flaky causes that actually occur in a Symfony + Doctrine DBAL codebase:
1. **Shared database/container state** — the test doesn't clean up, or reads state another test wrote. Missing reset in setup/teardown, or fixtures created without unique ids.
2. **Test ordering** — assumes it runs after another that seeded data; passes alone, fails in the full run.
3. **Time dependence** — `new DateTime()`/`time()` in test or code under test, TZ assumptions, "now"-relative assertions not frozen or injected via a clock abstraction.
4. **Non-deterministic ordering** — a query without an explicit `ORDER BY`, or asserting on `UlidValue`/insertion order; results come back in different orders across runs.
5. **Async / bus races** — the action dispatches through one of the four named buses (`command.bus`/`query.bus`/`event.bus`/`external_message.bus`) or `MessageStoreMiddleware` runs a side effect, and the assertion checks before it settles. Only relevant if this project has wired an async transport for a bus — check `config/packages/messenger.yaml` before assuming this applies.
6. **Randomized fixture data** — a test-data builder's random default occasionally produces a value that collides or violates the assertion; the test should pin the field under test explicitly instead of relying on a random default.
7. **Leaked test double / state** — a mock or static left set from a prior test.

Ground the diagnosis in the code (cite `file:line`) — don't guess from the name.

### 4. Propose the fix
Present root cause + the smallest fix, then confirm via **AskUserQuestion** (Apply / Investigate further / Provide more context) — unless the cause is unambiguous and trivial. Prefer, in order: fix the production bug the test exposes → isolate the test's state in setup/teardown → freeze/inject time → add explicit `ORDER BY` / pin the asserted value → await the async side effect deterministically instead of timing.

### 5. Apply (on confirmation)
- Minimal change; `declare(strict_types=1);` stays; snake_case test method (per CS Fixer's `php_unit_method_casing`).
- Branch (if not already on one): check `git branch -a` / recent history for this repo's naming convention; if none is established, `fix/flaky-<short>` off `main` is a reasonable default.

### 6. Verify stability — the gate of this skill
Re-run the fixed test enough times to trust it; **all** must pass:
```bash
PASS=0; FAIL=0
for i in $(seq 1 15); do
  docker compose run --rm app vendor/bin/pest <path> --filter '<test_method_name>' -q \
    && PASS=$((PASS+1)) || FAIL=$((FAIL+1))
done
echo "stable: $PASS passed / $FAIL failed of 15"
```
If the flake was ordering/shared-state, also re-run the file/directory that reproduced it. **Any** failure → back to step 3. If you can't reach all-green after ~3 fix iterations, stop and report honestly with the evidence (don't ship a "fix" that doesn't hold). Then run the normal gates before handing off:
```bash
make run-phpstan
make run-cs-fixer
```

### 7. Report & hand off
Summarize: the **mechanism** (one paragraph, specific), the **fix**, and **verification** (e.g. "15/15 sequential + file-level reproduced-then-green"). Then point to `/code-review` before opening the PR. Don't auto-commit or push.

## Notes

- The proof IS running it repeatedly green — a flaky fix without a stable re-run series is not done (this is the whole point of the skill).
- Reproduction/verification are sequential by design (one shared container). If a flake only appears under CI parallelism that you can't reproduce locally, say so and reason from the code rather than inventing a parallel runner.
- Conservative repo: if the real fix is in production code (a genuine race in a bus handler), flag the blast radius before changing it rather than patching the test to paper over a real bug.
