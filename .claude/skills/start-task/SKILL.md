---
name: start-task
description: Start work on a Notion task — resolve the task (and its spec under docs/specs/ if one exists), create a correctly-named branch, and assemble the implementation brief (requirements + constraints + relevant files) so anyone can go from task to PR consistently. Hands off to code-review / validate-pr. Triggers like "empieza la tarea", "arranca esta tarea", "me pongo con la tarea", "start task".
argument-hint: "[Notion task URL/ID or description] [optional spec slug]"
allowed-tools: Bash, Read, Grep, Agent, AskUserQuestion, Skill
---

# start-task

Bootstraps work on a single task so implementation starts from the **same shared context** the task was written with: the Notion task itself **plus**, if one exists, the spec produced by `/spec`. It sets up the branch and produces an implementation brief — it does **not** write the feature code.

## Arguments

- `$1` (optional): a Notion task URL/ID, or a free-text description to search for. If omitted, ask.
- `$2` (optional): an explicit `docs/specs/<slug>.md` path, when auto-detection is ambiguous.

## Steps

1. **Resolve the task.** Use the `notion-fetching-task` skill to fetch and structure it. Capture: the requirement/problem statement, any explicit acceptance criteria, and every comment (often holds design decisions made after the task was written).
2. **Look for a matching spec.** Check `docs/specs/` for a file whose slug matches the task title (use `$2` if given). If none exists, proceed from the task alone — a spec is extra context, not a precondition; suggest running `/spec` first only if the task looks non-trivial and under-specified.
3. **Create the working branch** off the right base.
   - Base: `main` by default — confirm via `AskUserQuestion` if the task clearly builds on another in-progress branch.
   - Name: derive a short slug from the task title, e.g. `feature/invite-token-validation`. Check `git branch -a` / recent history first in case this repo has already established a different convention; otherwise this is a reasonable default. If a suitable branch already exists, switch to it instead of recreating.
4. **Locate where the code goes.** Read `CLAUDE.md` for the architecture map (the `Shared` CQRS/messaging kernel, existing bounded contexts) and `Grep`/`Glob`/`Agent` to point at the real classes, buses, and tests this task touches. Don't implement; just map the surface.
5. **Emit the implementation brief** (in the user's language):
   - **Goal** — one line.
   - **Checklist** — every requirement/acceptance-criteria bullet from the task, plus every "must / should / do NOT / only" constraint from its description and comments, as checkable items.
   - **Invariants (must hold) vs Approach (suggested way)** — only if a matching spec was found in step 2; otherwise omit this split and just list the requirements flat.
   - **Where** — the concrete files/classes/tests to add or change (`file:line` where known).
   - **Watch-outs** — relevant `CLAUDE.md` conventions for this surface (layer boundaries per `tests/Arch/`, PHPStan checked exceptions, which named bus a new command/query/event should go through, whether it needs to be `Storable`).
   - **Next** — implement → `/code-review` → `validate-pr` (once available) → open the PR.

## Notes

- The only write is **creating/switching the git branch** (step 3) — confirm the base before creating. Never auto-commit or auto-implement; the developer drives the code.
- The **task is the source of truth**; a matching spec adds the agreed design. If the spec looks stale versus newer task comments, surface the discrepancy rather than trusting the spec blindly.
- Don't duplicate `validate-pr`'s job (it checks a finished diff against the task+spec). This skill is the *front* of the loop: task+spec → ready-to-code context.
