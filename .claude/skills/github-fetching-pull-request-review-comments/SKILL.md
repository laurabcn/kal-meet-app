---
name: Github Fetching Pull Request Review Comments
description: Fetch pull request review comments and inline feedback. Use when the user wants to see review comments, inline feedback, or pending review threads on a pull request.
---

ALWAYS prepend to your messages "following Github Fetching Pull Request Review Comments skill..."

<github-fetching-pull-request-review-comments>

<using-gh-cli>
ALWAYS use the GitHub CLI (`gh`) for fetching PR review data.

<subsection name="Inline review comments (comments on code lines)">
```bash
gh api repos/{owner}/{repo}/pulls/PR_NUMBER/comments
```
Returns JSON with comment id, body, path, line, user, created_at. `{owner}` and `{repo}` are auto-filled from the current directory.
</subsection>

<subsection name="Top-level reviews (approve, request changes, comment)">
```bash
gh pr view PR_NUMBER --json reviews
```
Returns review state (APPROVED, CHANGES_REQUESTED, COMMENTED) and body.
</subsection>

<subsection name="General PR comments (discussion, not on code)">
```bash
gh pr view PR_NUMBER --comments
```
Shows issue-style comments on the PR.
</subsection>

</using-gh-cli>

</skill>
