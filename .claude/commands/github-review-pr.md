---
name: github-review-pr
description: Review a pull request and post inline comments. Uses the reviewing-code and github-posting-pull-request-review skills.
---

<command name="Review Pull Request">

You review a pull request end-to-end: analyze the diff, produce file-by-file findings, and post inline comments on GitHub.

<tone>
Write like a human colleague. Be concise and direct. No emojis. No filler phrases ("Great question!", "Let me help you with that!"). No excessive formatting. Say what matters, skip the rest. Comments should sound like a senior engineer talking to a peer — specific, helpful, zero fluff.
</tone>

<input>
The user provides a PR number, URL, or branch name. If none is provided, ask for it.

Extract the PR number:

| Input Format | Example | Extract |
|--------------|---------|---------|
| PR number | `123` | Use directly |
| Full URL | `https://github.com/laurabcn/kal-app-backend/pull/123` | Extract number from path |
| Branch name | `feature/invite-links` | Find PR via `gh pr list --head <branch>` |
</input>

<step-1-setup>
Checkout the PR branch: `gh pr checkout PR_NUMBER`.
</step-1-setup>

<step-2-build-review-plan>
Build the review plan using the **reviewing-code** skill.

Present the plan to the user and ask:

> Here's my review plan. **Before I start reviewing, please check:**
> - Are there conventions missing that I should load?
> - Are there files I should skip or pay extra attention to?
> - Any context about this PR I should know?

**STOP and wait for user response.** If the user adds conventions, reload them. If the user removes files, skip them. Once confirmed, proceed.
</step-2-build-review-plan>

<step-3-file-by-file-review>
For each file in the plan, apply the review procedure and checks from the **reviewing-code** skill. Write comments in the specified format.

After all files, present the review summary (severity table).
</step-3-file-by-file-review>

<step-4-post-comments>
If the review produced Major or Blocker findings:

1. Present all proposed comments to the user as a numbered list with file, line, severity, and body.
2. **STOP and wait for confirmation.** The user may edit, remove, or add comments.
3. After confirmation, use the **github-posting-pull-request-review** skill to post each comment.
</step-4-post-comments>

<output>
After posting (or if no issues found), report how many comments were posted and the severity breakdown.
</output>

</command>