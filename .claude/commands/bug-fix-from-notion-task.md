---
name: bug-fix-from-notion-task
description: Analyze and fix a bug from a task on the Notion board. Fetches the task, gathers whatever context is reachable, diagnoses root cause, proposes fixes, and — in fix mode — after confirmation creates a branch, implements the fix with a regression test, runs the gates, and hands off to /code-review. Has an investigate-only mode that stops at the diagnosis. Uses the notion-fetching-task skill.
argument-hint: "[Notion task URL/ID or description] [--investigate]"
---

<command name="Bug Fix from Notion Task">

You analyze and fix bugs described in a Notion task. Fetch the task, gather available context, diagnose the root cause, propose fixes, and — in fix mode — implement after user confirmation.

<tone>
Direct and technical. Report findings clearly with specific file and line references. Write like a senior engineer diagnosing an issue — concise, precise, actionable.
</tone>

<mode>
Two modes share the same diagnosis (steps 1–4); they differ only in what happens after:

- **fix** (default) — diagnose → propose → confirm → branch + fix + test + gates + handoff. Steps 1–7.
- **investigate** — diagnose **only**. Run steps 1–4, deliver the root-cause report, then **stop**: no `AskUserQuestion` for a fix, no branch, no code, no gates. End with a short *"to fix this, re-run in fix mode"* pointer. Steps 5–7 are skipped entirely.

Pick **investigate** when: the user says "solo investiga / diagnostica / qué está pasando / por qué falla", passes `--investigate`, or only wants to understand the bug. Otherwise default to **fix**. When phrasing is genuinely ambiguous, ask once with `AskUserQuestion` before step 5.
</mode>

<input>
The user provides a Notion page URL, ID, or a description to search for. If missing, ask for it.
</input>

<step-1-fetch-task>
Fetch the task content and build the structured brief. Use the **notion-fetching-task** skill for how.
</step-1-fetch-task>

<step-2-analyze-task-content>
Read the task's description and metadata for:

1. **Bug description text** — reported problem, reproduction steps, expected vs actual behavior.
2. **Links to external context** — any URLs in the task content.

For each URL found, classify it:
- **Publicly fetchable** (e.g. a public GitHub issue, a public doc) → fetch it directly with WebFetch.
- **Requires auth this session doesn't have** (a private thread, an internal dashboard, a tool with no MCP connected) → you cannot access it; note it and ask the user to paste the relevant content.

After analyzing, present a brief summary of what you found, what you could fetch, and what you couldn't, then proceed to Step 3.
</step-2-analyze-task-content>

<step-3-pause-for-additional-context>
**Only if there are links you could not fetch.** Use the `AskUserQuestion` tool:

> Here's what I found in the task:
> - Bug description: [brief summary]
> - Links I could fetch: [list, or "none"]
> - Links I couldn't access: [list, or "none"]
>
> Could you paste the relevant content from the links I can't access? Error
> messages, logs, or reproduction details all help.

Offer these choices:
- **I'll paste additional context** — wait for the user to provide it, then incorporate it.
- **Proceed with what we have** — move forward with the available context.

**Do NOT investigate code, search the codebase, or start diagnosing until the user responds to this question**, unless there were no inaccessible links at all (then skip straight to Step 4).
</step-3-pause-for-additional-context>

<step-4-diagnose-and-propose>
Using all gathered context (task description + fetched links + anything the user pasted):

1. Identify the root cause from stack traces, error messages, and the task description. Ground it in the actual code (`Read`/`Grep`) — cite `file:line`, don't guess from names.
2. Present to the user:
   - **Root cause analysis** — what is failing and why, with the evidence that confirms it.
   - **Affected code** — specific files and lines involved, and which other classes (handlers, bus adapters, tests) touch the same surface.
   - **Proposed fix(es)** — one or two concrete options with what you would change and why. Label them **Option A**, **Option B** if there are multiple. Prefer the smallest correct change.

**In investigate mode, stop here.** Deliver the report above and end with a one-line pointer: *"to apply this, re-run in fix mode."* Do not run step 5 or beyond.
</step-4-diagnose-and-propose>

<step-5-confirm-next-action>
*(fix mode only)* **Use the `AskUserQuestion` tool** to let the user decide the next step:

- **Option A: Implement [brief description of option A]** — if you proposed a clear primary fix.
- **Option B: Implement [brief description of option B]** — only if you proposed a second alternative.
- **Investigate further** — the proposed options don't look right; dig deeper into the codebase or ask for more logs.
- **Provide more context** — the user wants to paste additional info before deciding.

Adapt the options to the situation: if there is only one clear fix, show just that option plus "Investigate further" and "Provide more context".

**Act on the user's choice:**
- **Implement** → proceed to step 6.
- **Investigate further** → go deeper into the codebase, then return to step 4 with updated diagnosis.
- **Provide more context** → wait for user input, then return to step 4 with updated diagnosis.
</step-5-confirm-next-action>

<step-6-implement>
*(fix mode only, after the user picks a fix)*

- **Branch**: if not already on a suitable branch, create one off `main` — a short slug from the bug/task title (e.g. `fix/invite-token-expiry`). If a suitable branch already exists, switch to it.
- Apply the chosen fix as the **minimal** change. `declare(strict_types=1);` stays. Don't touch anything outside the bug's scope to make a gate pass.
- **Add or adjust a test that fails before and passes after** — Pest, snake_case method names (per CS Fixer's `php_unit_method_casing`). A bug fix without a regression test is incomplete.
</step-6-implement>

<step-7-verify-and-handoff>
*(fix mode only)*

Run and report results — green before done:
```bash
make run-phpstan
make test
make run-cs-fixer   # dry-run: no changes made
```
If anything fails, report the actual output — don't paper over it. Then point to `/code-review` before opening the PR. Don't auto-commit or push.
</step-7-verify-and-handoff>

<output>
- Task brief (title, problem)
- Context fetched or provided by the user
- Root cause analysis
- Proposed fix(es) with file and line references
- *(fix mode)* branch created, fix + test applied, gate results

Always confirm with the user before implementing any code changes.
</output>

</command>
