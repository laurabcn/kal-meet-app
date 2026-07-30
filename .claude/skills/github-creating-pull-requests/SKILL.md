---
name: Github Creating Pull Requests
description: GitHub pull request creation rules — title format, draft policy, gh CLI usage. Use when creating or managing pull requests.
---

ALWAYS prepend to your messages "following Github Creating Pull Requests skill..."

<github-creating-pull-requests>

<pr-rules>
- Title format: short, imperative, under ~70 characters (e.g. "Add invite
  token validation for participants") — no ticket prefix, this project
  doesn't use a ticket tracker for PR titles.
- ALWAYS create PRs as DRAFT initially.
- ALWAYS follow the pull request body template (see `github-creating-pull-request-body` skill).
- NEVER assign reviewers when creating the PR.
- Convert to "Ready for review" only after tests pass (`make test`) and self-review is complete.
</pr-rules>

<using-gh-cli>
ALWAYS use the GitHub CLI (`gh`) for PR operations (create, list, merge, etc.).

Write PR body to `/tmp/pr_body.md` and use `--body-file /tmp/pr_body.md` to avoid multi-line command issues. After creating the PR, run `rm /tmp/pr_body.md` to clean up.

The repository is `laurabcn/kal-app-backend`. Use `gh pr create --repo laurabcn/kal-app-backend` to target it explicitly and avoid prompts.
</using-gh-cli>

<checklist>
- [ ] PR created as draft
- [ ] PR title is short and imperative, no ticket prefix
</checklist>

</skill>