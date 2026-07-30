---
name: validate-pr
description: Validate the current branch's changes against its task — a Notion task and/or the spec produced by /spec — and report which requirements are met, partial or missing, with file evidence and test-coverage gaps. Use before opening or approving a PR. Triggers like "valida la PR", "valida la rama contra la tarea", "validate this PR against its task".
argument-hint: "[Notion task URL/ID, or spec slug] [base-branch]"
allowed-tools: Bash, Read, Grep, Agent, AskUserQuestion, Skill
---

# validate-pr

Validates the current branch's diff against **its task**, and reports whether the implementation satisfies it. The spec is whichever of these exist: the Notion task's requirements + comments, and/or the file `/spec` produced at `docs/specs/<slug>.md`. The work captured there is the acceptance gate.

This is **not** a generic code review (use `/code-review` for bugs/quality). It checks one thing: *does the diff do what this task says, the way the task says?*

## Arguments

- `$1` (optional): a Notion task URL/ID, or a `docs/specs/<slug>.md` slug. If omitted, look for a `docs/specs/` file whose slug matches the current branch name; if none matches and no task was given, ask which task/spec this PR is for.
- `$2` (optional): base branch override, e.g. `main`. Use when the auto-detected base is wrong. To pass only `$2`, pass `""` for `$1`.

## Steps

1. **Resolve the task/spec.** Per the argument rules above. If a Notion task is available, fetch it with the `notion-fetching-task` skill. If a spec file exists at `docs/specs/<slug>.md`, read it too — both may exist for the same piece of work.
2. **Build the checklist.** Extract every acceptance-criteria bullet **and** every "must / should / do NOT / only" constraint from the task's description and comments into a flat list of checkable items. If a spec file exists, add its requirements too (see the Invariants-vs-Approach note below).
3. **Get the diff.** Detect the base (merge-base against `main`, or the parent feature branch if this is a sub-branch; `$2` overrides). Run `git diff <base>...HEAD --stat` and the full diff. List changed files.
4. **Evaluate each item** against the diff. Read code where needed (`Read`/`Grep`/`Agent`). Mark each:
   - ✅ **met** — cite `file:line`.
   - ⚠️ **partial / unclear** — explain what's missing or ambiguous.
   - ❌ **missing** — not implemented.
   - ➖ **N/A** — doesn't apply to this diff.
5. **Tests.** For every requirement that implies test coverage, confirm a matching test exists in the diff; list any coverage gap. Remember CI, not the pre-commit hook, is what actually runs the suite — a missing test is a real risk, not a nit.
6. **Design-constraint violations.** Explicitly flag anything in the diff that contradicts a decision recorded in the task's comments or the spec (e.g. logic placed in the wrong layer, a bus bypassed in favor of calling a repository directly from a controller, a `Storable` message left unpersisted). These are the highest-signal findings — ground each one in `CLAUDE.md` or the spec, not personal preference.
7. **Report.** Output, in the user's language:
   - **Verdict**: `Ready` / `Needs work` (+ one-line why).
   - **Checklist**: each item with its status + evidence (`file:line`).
   - **Gaps & risks**: missing items, test gaps, design-constraint violations.
   - **Out of scope / follow-ups**: anything in the diff not covered by the task/spec.

## Notes

- **Read-only.** Never edit code; just report. (If the user later wants the report as a PR comment, that's a separate explicit step.)
- **Spec (optional, `docs/specs/`):** when a spec file exists, it may separate **Invariants** from **Approach** (see the `/spec` template) — treat them differently:
  - **Invariants** = outcomes that MUST hold → a broken one is a real **miss**, flag it.
  - **Approach** = the current agreed *way* → if the PR does it differently **but the invariants still hold**, report it as *"alternative approach, invariants met"*, NOT a failure. Don't penalise a cleaner solution.
  - Also flag out-of-scope work.
- The task is the source of truth — if the diff is reasonable but the task description is stale, say so rather than forcing a pass/fail.
- Keep evidence concrete: cite real `file:line` from the diff, not assumptions.
