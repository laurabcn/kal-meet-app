---
name: github-create-pr
description: Create a pull request from the current branch. Uses the github-managing-branches, github-writing-commits, github-creating-pull-request-body, and github-creating-pull-requests skills.
---

<command name="Create Pull Request">

You create a pull request from the current branch: verify the branch, generate a PR body, and open a draft PR on GitHub.

<tone>
Write like a human colleague. Concise, direct, no filler. The PR body should read like a developer explaining what changed and why to a reviewer — specific, helpful, zero fluff.
</tone>

<input>
The user provides a task description or simply asks to create a PR.

If on `main`, ask the user which branch to use or whether to create one.
</input>

<step-1-verify-branch>
Follow the **github-managing-branches** skill to confirm the branch is
correctly named and based on latest `main`. If on `main`, ask the user to
specify or create a branch.
</step-1-verify-branch>

<step-2-verify-commits>
Check that the branch has commits ahead of `main`:

```bash
git log --oneline origin/main..HEAD
```

If no commits, tell the user there is nothing to open a PR for.

Present the commit list to the user for awareness. Follow the
**github-writing-commits** skill for what a good commit looks like if any
need cleaning up first.
</step-2-verify-commits>

<step-3-offer-self-review>
Before proceeding, ask:

> Want me to run a code review on your changes before creating the PR?

If the user accepts, follow the **reviewing-code** skill to review the diff against `main`. Present findings. If there are issues, let the user fix them before continuing.

If the user declines, proceed to the next step.
</step-3-offer-self-review>

<step-4-push-branch>
Ensure the branch is pushed to the remote:

```bash
git push -u origin HEAD
```

If the push fails, report the error and stop.
</step-4-push-branch>

<step-5-generate-pr-body>
Use the **github-creating-pull-request-body** skill to generate the PR body.

Present the generated PR body to the user.

**STOP and wait for confirmation.** The user may edit the title, body, or ask for changes.
</step-5-generate-pr-body>

<step-6-create-draft-pr>
After confirmation, use the **github-creating-pull-requests** skill to create the draft PR against `laurabcn/kal-app-backend`.
</step-6-create-draft-pr>

<output>
Report the PR URL, title, and draft status.
</output>

</command>