---
name: Github Writing Commits
description: Git commit message guidelines - Conventional Commits, atomic changes. Use when committing code changes.
---

ALWAYS prepend to your messages "following Github Writing Commits skill..."

<github-writing-commits>

<commits>
ALWAYS make modular commits with atomic changes — one logical change per commit.

ALWAYS use Conventional Commits format: `type: description` or `type(scope):
description` (e.g. `feat(kals): add POST endpoint`, `fix: return persisted
entity with real created_at`). Common types: `feat`, `fix`, `refactor`,
`chore`, `docs`, `test`.

ALWAYS write the description in imperative mood (e.g. "add", not "added" or
"adds"), max ~72 characters for the subject line.

ALWAYS separate refactoring commits from feature commits.

NEVER use vague commit messages like "WIP", "fixes", "updates", or "done".

NEVER mix formatting changes with logic changes in the same commit.
</commits>

<language-guidelines>
- Use direct, simple language without technical fluff
- NEVER use words like "enhancement", "improvement", "optimization" in the
  subject line — use concrete terms: "fix", "add", "remove", "change", "update"
- Write as if explaining to a colleague, not writing marketing copy
- Be concise but complete - say what needs to be said, nothing more
- The commit body (if any) explains WHY, not WHAT — the diff already shows what changed
</language-guidelines>

<checklist>
- [ ] Commit message follows Conventional Commits (`type: description`)
- [ ] Description is imperative mood, subject line under ~72 chars
- [ ] Atomic commits (one logical change per commit)
</checklist>

</skill>