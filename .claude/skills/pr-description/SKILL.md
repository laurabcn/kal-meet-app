---
name: pr-description
description: Generate a PR description in English for the current branch following .github/pull_request_template.md. Optionally links a Notion task, diffs against the auto-detected base branch, and copies the result to the macOS clipboard. Use when the developer is about to open or update a PR.
argument-hint: "[Notion task URL/ID] [base-branch]"
allowed-tools: Bash, Read, AskUserQuestion, Skill
---

# pr-description

Drafts a PR description for the current branch and copies it to the macOS clipboard so the developer can paste it into the GitHub PR page.

Unlike a Jira-ticket-key-in-branch-name convention, this repo has no fixed rule tying a branch to a task — so the task reference is **explicit input**, not parsed from the branch name.

## Arguments

- `$1` (optional): a Notion task URL/ID. If given, fetch it (via the `notion-fetching-task` skill) and use it as the primary source for the "why". If omitted, derive the "why" from commits + diff alone — no error, just less context.
- `$2` (optional): Base branch override, e.g. `develop` or a parent feature branch. Use when the auto-detected base is wrong.

Either argument may be omitted. To pass only `$2`, pass an empty string for `$1`: `/pr-description "" feature/parent-branch`.

## Workflow

Execute the phases below sequentially. Run independent shell commands within a phase in parallel (single message, multiple Bash calls) for speed.

### Phase 1 — Parse args & detect branch

1. Capture `$1` as `TASK_REF` and `$2` as `BASE_OVERRIDE`. Empty string = not provided.
2. Run `git rev-parse --abbrev-ref HEAD` to get `CURRENT_BRANCH`.
3. Abort with a clear message if any of the following:
   - `git rev-parse --is-inside-work-tree` is not `true` (not a git repo).
   - `CURRENT_BRANCH` is `HEAD` (detached).
   - `CURRENT_BRANCH` is `main` or `master` (cannot make a PR from a base branch).

### Phase 2 — Detect base branch (auto-detect + confirm)

If `BASE_OVERRIDE` is set, use it as `BASE_BRANCH` and skip to Phase 3.

Otherwise:

1. Refresh remotes lightly: `git fetch --quiet origin main 2>/dev/null || true`.
2. Build the candidate list:
   - `origin/main` (always)
   - The upstream of the current branch if set: `git rev-parse --abbrev-ref --symbolic-full-name @{upstream} 2>/dev/null` — but **only if it is not the current branch's own remote tracking ref**. Filter out any candidate whose name (after stripping `origin/`) equals `CURRENT_BRANCH`.
   - Any remote feature branch that is an ancestor of HEAD and not equal to HEAD — to find these cheaply: `git for-each-ref --format='%(refname:short)' refs/remotes/origin/ | grep -E '^origin/(feature|fix|bugfix|chore|hotfix)/' | head -20` and then filter by `git merge-base --is-ancestor <candidate> HEAD` returning success **and** `<candidate>` not pointing at the same commit as HEAD.
3. For each surviving candidate compute commits-ahead: `git rev-list --count <candidate>..HEAD`. Drop any candidate with `0` ahead (same commit as HEAD).
4. Sort ascending by commits-ahead. The smallest is the most likely base.
5. Decision:
   - If only one candidate remains, use it.
   - If the winner is `origin/main` and the next candidate has ≥ 1 more commit-ahead, use `origin/main` silently.
   - Otherwise call `AskUserQuestion` listing the top 2–3 candidates with their commits-ahead counts so the developer confirms. Header: "Base branch".
6. Store as `BASE_BRANCH`.

### Phase 3 — Gather change context

Run in parallel:

- `git log "$BASE_BRANCH..HEAD" --no-merges --pretty=format:'%h %s'` — commit subjects.
- `git diff "$BASE_BRANCH...HEAD" --stat` — files changed summary (triple-dot: changes on the branch only).
- `git diff "$BASE_BRANCH...HEAD" -- ':!**/composer.lock' ':!**/*.lock'` — pipe through `head -c 60000` (~600 lines max) to cap context.

If commits-ahead is `0`, abort: "Nothing to describe — `$CURRENT_BRANCH` has no commits ahead of `$BASE_BRANCH`."

### Phase 4 — Fetch the Notion task (optional)

Only if `TASK_REF` is non-empty. Use the `notion-fetching-task` skill. If it fails (no MCP, page not found), continue with `TASK_TITLE=""` and `TASK_DESCRIPTION=""` and remember to warn the user at the end.

### Phase 5 — Render the description

Read `.github/pull_request_template.md` to make sure the structure is still the canonical one — **prefer the on-disk version** if it has drifted from what's reproduced below:

```markdown
### Context
Why is this change necessary? What problem or task does it address?

--- 
### Solution
Briefly explain the approach taken through this Pull Request to address the requirements above.

--- 
### Test plan
How was this verified? (tests added/updated, `make qa` output, manual verification steps)

--- 

🔗 **Related** (if any — remove otherwise):
```

Rendering rules:

- **Language: English only**, regardless of the task's language. Translate if needed.
- **Preserve the template verbatim**: same section headings, same `--- ` separators (the trailing spaces on the dividers are intentional — keep them), same blank-line structure.
- **`### Context`** — replace the placeholder with 2–4 sentences explaining *why* this change exists. Ground the text in:
  - `TASK_TITLE` + `TASK_DESCRIPTION`, if a task was resolved (primary source for the "why").
  - The commit subjects and diff stat (secondary — to validate the task is actually the scope of the branch, or the sole source if no task was given).
  - Do not invent constraints, deadlines, or stakeholders that are not in either source.
- **`### Solution`** — replace the placeholder with 3–6 markdown bullets describing the change at an architectural level: new endpoint, new command/query/event and which bus it goes through, new bounded-context class, migration, etc. Name the actual classes/bounded contexts touched (read from the diff — don't assume names from another project). Avoid line-by-line diff narration; favor signal over noise.
- **`### Test plan`** — describe what was actually verified: new/changed tests in the diff (name them), and `make qa` result if you ran it. If no tests were added for a behavioral change, say so plainly rather than omitting it — that's information the reviewer needs.
- **`🔗 **Related**`** — if a task was resolved, add its Notion URL as a bullet. If none was given, keep the placeholder line untouched so the developer can decide whether to remove the block.

### Phase 6 — Copy to clipboard and report

1. Write the rendered description to a temporary file and pipe it into `pbcopy` (avoids shell escaping issues with backticks, `$`, emojis, multiline content):

   ```bash
   TMP=$(mktemp -t pr-description.XXXXXX.md)
   cat > "$TMP" <<'PR_EOF'
   <rendered description here>
   PR_EOF
   pbcopy < "$TMP"
   rm -f "$TMP"
   ```

   Use a quoted heredoc (`<<'PR_EOF'`) so the shell does not expand anything inside the description.

2. Print the rendered description verbatim to the chat inside a fenced code block so the developer can review what is now on the clipboard.

3. Print a one-paragraph summary with:
   - Resolved task (or "no task given — Context drawn from commits/diff only").
   - Detected base branch and commits-ahead count.
   - Suggested next step: open the PR page on GitHub. Use `gh pr view --web 2>/dev/null || gh pr create --web` so existing PRs are opened, otherwise the create flow.

## Notes for the executing agent

- Do not commit, push, or create the PR yourself. The skill stops at "copied to clipboard and ready to paste".
- Do not modify files in the working tree.
- Keep the chat output tight: one progress line per phase is enough. The full description is the deliverable.
- The PR template's divider lines (`--- `) end with a trailing space — preserve them exactly when rendering.
