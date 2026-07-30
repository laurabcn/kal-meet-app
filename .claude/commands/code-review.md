---
name: code-review
description: Review pending changes against this repo's conventions (DDD/hexagonal via the Shared kernel, PHPStan max with checked exceptions, CS Fixer, strict_types) plus bugs, edge cases, and a first-class static review of tests (test quality + coverage-gap analysis). Three forms by scope — staged/uncommitted changes, the whole branch vs its base, or a GitHub PR by number/URL — all delegating to the same code-reviewer subagent. Read-only. Triggers like "revisa el código", "code review", "revisa la rama", "revisa la PR 123", "review changes before commit".
argument-hint: "[scope: empty=staged | 'branch' | base-branch | PR number/URL]"
allowed-tools: Bash, Read, Grep, Agent
---

# code-review

Runs a repo-aware code review of pending changes. It is a **thin dispatcher**: it resolves the diff for the chosen *form*, then hands that diff to the **`code-reviewer`** subagent, which holds all the review knowledge and returns the report. The subagent is the single source of truth — the three forms differ **only in which diff they feed it**.

Supersedes the generic plugin `code-review` for this repo: this one knows the conventions in `CLAUDE.md` (DDD layering via `Shared`, PHPStan at level max with checked exceptions, CS Fixer single-source, the four named message buses, `Storable*` messages). **Read-only** — it never edits, commits, or posts anywhere.

## The three forms (pick by `$1`)

| `$1` | Form | Diff fed to the subagent |
|---|---|---|
| *(empty)* | **1 — Staged / uncommitted** | `git diff HEAD` (working tree + staged). If nothing uncommitted, fall back to `git diff --staged`; if still empty, say so. Fast gate before `git commit`. |
| `branch` or a base branch name | **2 — Branch vs base** | `git diff <base>...HEAD` — the whole branch as **CI will see it**. Detect `<base>` via merge-base against `main` (or the parent feature branch); an explicit branch name in `$1` overrides. |
| a PR number or GitHub URL | **3 — GitHub PR** | `gh pr diff <n>` for that PR. Use for reviewing someone else's PR. |

If `$1` is ambiguous, ask which form with `AskUserQuestion` (or infer: all-digits / `github.com/...` → form 3; a ref that exists → form 2).

## Steps

1. **Resolve the form** from `$1` (table above) and compute the diff range.
2. **Gather the diff + changed-file list.** Cap very large diffs (~60k chars) but always pass the full changed-file list so the reviewer knows the surface.
   - Form 1: `git diff HEAD --stat` + `git diff HEAD`.
   - Form 2: detect base (`git merge-base`), `git diff <base>...HEAD --stat` + the diff.
   - Form 3: `gh pr view <n> --json title,body,headRefName` for context + `gh pr diff <n>`.
3. **Delegate to the subagent.** Call the `Agent` tool with `subagent_type: "code-reviewer"` — the harness registers `.claude/agents/*.md` as its own type, so the role loads on spawn and the prompt carries only the task. Pass: the form/scope, the changed-file list, and the diff (or, for a local form, the base ref so it can read freely). Let the agent read the surrounding code itself — don't pre-summarise findings.
4. **Relay the report.** Present the agent's structured report (Verdict / 🔴 Blocking / 🟡 Non-blocking / 🧪 Tests / 📐 Conventions / ➕ Out of scope) in the user's language. Add nothing of your own except, if the verdict is "Ready", a one-line reminder that the real gates are still `make run-phpstan` + `make run-cs-fixer` + `make test` (i.e. `make qa`).

## Notes

- **One subagent, three diffs.** Don't reimplement the review logic here — that lives in `.claude/agents/code-reviewer.md`. If a convention changes, edit the agent, and all three forms get it.
- **Read-only.** No edits, no commits, no PR comments. (Posting the report as a PR comment would be a separate, explicit step.)
- For a deeper pass you can fan out: invoke the subagent more than once (e.g. one per bounded context touched) and merge the reports — but the default is a single call.
- Forms 2/3 mirror what CI/reviewers see; form 1 is the quick pre-commit self-check. None of them replaces actually running `make qa`.
