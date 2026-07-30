---
name: triage-pr-comments
description: Triage the review comments left ON your PR — fetch them, verify each against the actual code, classify as Problem / Suggestion / Noise, then fix, reply, or dismiss. The mirror of /code-review, which produces reviews. Triggers like "tría los comentarios de la PR", "responde a los comentarios de la review", "triage PR comments".
argument-hint: "[PR number or URL] (defaults to the PR of the current branch)"
allowed-tools: Bash, Read, Grep, Glob, Edit, Agent, AskUserQuestion, Skill
---

# triage-pr-comments

You triage the review feedback left on a pull request: pull every comment, **verify each against the real code** before believing it, classify it, and help the developer act. Reviewers can be wrong and the code can be wrong — your job is independent analysis, not siding with either. This is the downstream counterpart of `code-review`: that one *gives* reviews, this one *handles the ones you get*.

`gh` auto-fills owner/repo from the working directory.

## Arguments

- `$1` (optional): PR number (`123`) or URL. If omitted, resolve the PR for the current branch (`gh pr view --json number`). If there's none, say so and ask.

## Steps

### 1. Resolve the PR and load the code under review
```bash
gh pr view $1 --json number,title,headRefName,baseRefName,url
gh pr checkout <number>     # so you read the exact code the reviewer saw
```
Confirm you're on the PR's head branch before reading any code.

### 2. Fetch every comment
- **Inline comments** (on code lines): `gh api repos/{owner}/{repo}/pulls/<number>/comments` — keep `id`, `user.login`, `path`, `line`/`original_line`, `body`, `in_reply_to_id`.
- **Top-level reviews** (APPROVED / CHANGES_REQUESTED / COMMENTED + body): `gh pr view <number> --json reviews`.
- **General PR discussion**: `gh pr view <number> --comments`.

Drop your own already-posted replies and resolved/outdated threads from the work list (note how many you skipped). Present a one-row-per-comment catalog:

| # | Author | Type | File:Line | Comment (preview) |
|---|--------|------|-----------|-------------------|

Output the table as raw markdown — not inside a code fence, blank line before and after.

### 3. Verify & classify each comment
For every comment: **read the file and ±15 lines around the referenced line first** — never classify from the comment text alone. Pull in callers, tests, and the relevant aggregate/handler/bus adapter (`Grep`, and `CLAUDE.md` for the map) when the line doesn't tell the whole story.

Classify:
- 🔴 **Problem** — correctly identifies a bug, logic/edge-case error, security issue, **or a violation of a documented convention** (DDD/hexagonal layering via `Shared`, `strict_types`, PHPStan-max typing with checked exceptions, CS Fixer rules, the named-bus binding-by-constructor-parameter convention, `Storable*` message persistence). Must be fixed.
- 🟡 **Suggestion** — a valid improvement, but the current code isn't wrong.
- 🟢 **Noise** — incorrect, already handled elsewhere, a misread of the code, or pure subjective style with no standard behind it.

Severity guide: bug/logic error → 🔴. Missing edge case → 🔴 if it can fail at runtime, else 🟡. Design concern → 🟡 unless it breaks a documented convention → 🔴. Style/naming → 🟢 unless it breaks project standards (CS Fixer/PHPStan) → 🟡.

Present one **card per comment** (not a wide table), stacked:

```
### #1 — 🔴 Problem
**File**: src/Context/.../Foo.php:42 · **Author**: alice
**Comment**: "<reviewer's words>"
**Analysis**: <what the code actually does, file:line evidence, why the call stands or falls>
```

Group comments that point at the same underlying issue into one item.

### 4. Iterate with the developer
Confirm or override your classifications via **AskUserQuestion**, then walk the 🔴 first, then the 🟡 the developer wants to address. For each, offer:
- **Fix it** — apply the change now (see step 5), then show the diff.
- **Reply** — agree but defer, or push back: draft a reply to the reviewer and post it after confirmation (step 6).
- **Dismiss** — after discussion, reclassify as not-a-problem.
- **Skip** — next comment.

### 5. Applying a fix (only when the developer says so)
- Smallest correct change; stay in the bug's scope; `declare(strict_types=1);` stays; follow the conventions the comment invoked.
- If behaviour changes, add/adjust the regression test — Pest, **snake_case** test names.
- Run the gates and report results before calling it done:
  ```bash
  make run-phpstan
  make test
  make run-cs-fixer   # dry-run: no changes made
  ```
- For several fixes, optionally hand the final diff to `/code-review` before pushing.

### 6. Replying to a reviewer (only after confirmation)
Reply on the inline thread (COMMENT_ID in the path, not the body):
```bash
gh api repos/{owner}/{repo}/pulls/<number>/comments/<COMMENT_ID>/replies -f body="<reply>"
```
Long replies: write to a scratch file, use `-F body=@<path>`, then remove it. Keep replies factual and specific (reference the `file:line` / convention) — no flattery, no over-promising.

### 7. Summary
Close with a card-format recap grouped by resolution: 🔴 Fixed / 🟡 Acknowledged-or-replied / 🟢 Dismissed-or-skipped, each with `#`, `file:line` and the one-line outcome. If any fixes were applied, remind the developer to commit & push (and that pushing re-triggers CI).

## Notes

- **Read-only on GitHub by default.** The only writes are posting replies (step 6) and applying fixes (step 5) — each only after the developer confirms. Never resolve threads or change PR state automatically.
- Never dismiss a comment without reading the code it points at; never accept one without verifying it either. Be skeptical of both reviewer and author.
- A comment that cites a convention is only 🔴 if the code actually breaks it — check `CLAUDE.md`, don't take the reviewer's word for the rule.
