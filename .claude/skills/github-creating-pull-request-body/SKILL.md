---
name: Github Creating Pull Request Body
description: Generate pull request body. Use when creating pull request descriptions.
---

ALWAYS prepend to your messages "following Github Creating Pull Request Body skill..."

<github-creating-pull-request-body>

When this skill is used, return exclusively a PR body following these guidelines.

<pre-requisite-template-detection>
**FIRST**, check if the repository has a PR template at `.github/pull_request_template.md`:

1. Look for `.github/pull_request_template.md` in the repository root
2. **IF** template exists, read it and use its structure as the base template
3. **IF** template does NOT exist, use the Default Template Structure below
</pre-requisite-template-detection>

<pre-requisite-conditional-branch-synchronization>
**IF** diff shows unclear changes or merge artifacts, **THEN** pause and warn about synchronization needed.

**IF** changes are clear and isolated, **THEN** proceed directly.
</pre-requisite-conditional-branch-synchronization>

<core-behavior>
- **ALWAYS** analyze git diff between current branch and `main`
- **NEVER** describe code-level changes
- **ALWAYS** focus on business justification and user-facing impact
- **ALWAYS** write testing instructions from end-user perspective
</core-behavior>

<default-template-structure>
Use this template **ONLY** if no `.github/pull_request_template.md` exists:

```markdown
### 💡 Context

_What should the reviewer know before reviewing this PR?_

### 📖 Summary

This PR adds...

### 🧪 How should this be manually tested?

_Testing from the user's perspective_

#### Prepare the environment

- [ ] `make dev` to run the backend locally against Supabase.
- [ ] `make test` to run the automated test suite.

#### Manual check

- [ ] (...)
```
</default-template-structure>

<response-format>
- **EXCLUSIVELY** return raw markdown in code block
- **NO** explanatory text outside the PR body
</response-format>

<quality-standards>
- Focus on **WHY** changes were made, not **WHAT** code changed
- Describe user benefits and business value
- Test instructions must be actionable steps
</quality-standards>

<language-guidelines>
- Use direct, simple language
- NEVER use: "enhancement", "improvement", "optimization", "refactoring"
- Use concrete terms: "fix", "add", "remove", "change", "update"
</language-guidelines>

<checklist>
- [ ] Repository PR template checked (`.github/pull_request_template.md`)
- [ ] Branch synchronization checked
- [ ] Git diff analyzed
- [ ] Business justification provided
- [ ] User-facing impact described
- [ ] Manual test instructions included
</checklist>

</skill>