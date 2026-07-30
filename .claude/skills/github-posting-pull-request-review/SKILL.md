---
name: Github Posting Pull Request Review
description: How to post inline review comments on a pull request via gh CLI. Use when posting code review feedback as inline comments on specific file lines.
---

ALWAYS prepend to your messages "following Github Posting Pull Request Review skill..."

<github-posting-pull-request-review>

<get-commit-sha>
```bash
gh pr view PR_NUMBER --json headRefOid -q .headRefOid
```
Use this as `commit_id` for each comment.
</get-commit-sha>

<post-one-inline-comment>
```bash
gh api repos/{owner}/{repo}/pulls/PR_NUMBER/comments -f body="COMMENT_TEXT" -f commit_id=COMMIT_SHA -f path="FILE_PATH" -F line=LINE_NUMBER -f side="RIGHT"
```
- `path`: relative path from repo root (e.g., `src/main.py`)
- `line`: line number in the file (new code side)
- `side`: `RIGHT` for new code (additions), `LEFT` for old code (deletions)

For long bodies, write to `/tmp/review_body.md` and use `-F body=@/tmp/review_body.md`. Run `rm /tmp/review_body.md` after posting.
</post-one-inline-comment>

<post-multiple-comments>
Run one `gh api` call per comment. Repeat for each file/line.
</post-multiple-comments>

</skill>
