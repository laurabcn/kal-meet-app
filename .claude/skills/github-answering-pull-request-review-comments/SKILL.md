---
name: Github Answering Pull Request Review Comments
description: Reply to inline review comments on a pull request via gh CLI. Use when posting replies to specific review comments.
---

ALWAYS prepend to your messages "following Github Answering Pull Request Review Comments skill..."

<github-answering-pull-request-review-comments>

<get-comment-id>
Fetch comments to get the ID:
```bash
gh api repos/{owner}/{repo}/pulls/PR_NUMBER/comments
```
Each comment has an `id` field. Use this for `in_reply_to`. `{owner}` and `{repo}` are auto-filled from the current directory.
</get-comment-id>

<post-reply>
Use the dedicated replies endpoint (COMMENT_ID in the path, not in the body):
```bash
gh api repos/{owner}/{repo}/pulls/PR_NUMBER/comments/COMMENT_ID/replies -f body="REPLY_TEXT"
```
For long replies, write to `/tmp/reply_body.md` and use:
```bash
gh api repos/{owner}/{repo}/pulls/PR_NUMBER/comments/COMMENT_ID/replies -F body=@/tmp/reply_body.md
```
Run `rm /tmp/reply_body.md` after posting.
</post-reply>

</skill>
