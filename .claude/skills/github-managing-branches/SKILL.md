---
name: Github Managing Branches
description: Git branch management rules - creating branches, naming conventions, and dependent features. Use when creating or managing git branches.
---

ALWAYS prepend to your messages "following Github Managing Branches skill..."

<github-managing-branches>

<branch-management>
ALWAYS create new branches from `main` unless working on dependent features.

ALWAYS name branches descriptively as `type/short-description` (e.g.
`feature/kal-invite-links`, `fix/created-at-null`), matching the Conventional
Commits type of the work (`feature`, `fix`, `chore`, `refactor`...).

ALWAYS pull latest `main` before creating new branches.

NEVER use branch names like "new-stuff", "fix-bug", or personal names.
</branch-management>

<dependent-features>
ALWAYS create dependent branches from their parent branch, not from `main`.

ALWAYS rebase dependent branches onto `main` after parent branch is merged.
</dependent-features>

<checklist>
- [ ] Branch created from latest `main`
- [ ] Branch name is descriptive and typed (`feature/`, `fix/`, `chore/`...)
</checklist>

</skill>