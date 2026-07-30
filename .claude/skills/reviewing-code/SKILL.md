---
name: Reviewing Code
description: Complete code review workflow — diff collection, convention enforcement, structured file review, severity classification, and comment formatting. Use when reviewing code changes, examining diffs, or auditing pull requests.
---

ALWAYS prepend to your messages "following Reviewing Code skill..."

<reviewing-code>

<core_principle>
    Prefer continuous improvement, but do not allow regressions in quality.
</core_principle>

<step-1-collect-the-diff>
```bash
git diff --name-only origin/main...HEAD
```

If a PR link is provided, checkout the branch first. Otherwise use the current branch.
</step-1-collect-the-diff>

<step-2-load-conventions>

<conventions>
    - Read `CLAUDE.md` at the repo root — it documents the architecture
      decisions (hexagonal + CQRS via the `Shared` kernel, DBAL without ORM,
      PHPStan at `level: max`, CS Fixer rules) and is the primary source of
      truth.
    - Load every `.claude/skills/*/SKILL.md` whose description matches a
      changed file's type or purpose (e.g. `github-writing-commits` for
      commit messages). This repo has no curated PHP skill set yet — most
      skills under `.claude/skills/` are for a different (Django/Python)
      project and won't match anything here.
    - Build a checklist from those conventions and enforce them on the diff.
    - If a rule or skill is not applicable, do not report it.
    - Ignore test files and testing conventions entirely.
    - Any code style comment must cite an explicit `CLAUDE.md` or skill rule.
    - Documented conventions are the single source of truth. When a
      convention says to use X, new code using X is CORRECT even if existing
      code in the same module uses Y. Never flag new code for being
      "inconsistent" with old code that violates a convention.
</conventions>
</step-2-load-conventions>

<step-3-match-conventions-to-files>
For each changed file, determine which conventions apply based on:

- File extension and path (e.g. `src/<Context>/Domain/` → hexagonal boundary rules)
- File type (domain entity, command/query handler, repository, router, test, migration, config)
- Skill description (match against file purpose)
</step-3-match-conventions-to-files>

<step-4-review-each-file>
For each file:

1. Read the complete file (not just the diff).
2. Apply all mapped conventions from the plan.
3. Evaluate the expert reviewer questions (see below).
4. Walk through the review checklist (see below).
5. Write comments as a human reviewer talking to the PR author.

If a file has no issues, state it explicitly.
</step-4-review-each-file>

<scope>
    <scope_include>
      - Design and architecture fit (cohesion, boundaries)
      - Correctness, edge cases, and failure modes
      - Data integrity, concurrency, and lifecycle management
      - Security and privacy risks (input validation, authz, secrets, logging)
      - Performance and resource usage (hot paths, N+1, memory, I/O)
      - Backward compatibility and contract changes
      - Documentation or rollout changes required by the change
    </scope_include>
    <scope_exclude>
      - Purely stylistic preferences not mandated by repo conventions
      - DRY/duplication findings (handled by duplication agent)
      - Test coverage gaps or missing tests (handled by test-coverage agent)
      - Changes outside modified lines and their immediate impact
      - "Consistency" suggestions: NEVER flag new code for differing from existing code
        unless `CLAUDE.md` or a skill mandates a specific approach.
        Existing code is not a convention. Only documented rules are conventions.
    </scope_exclude>
</scope>

<expert_reviewer_questions>
    1. Is this the simplest solution that solves the stated problem?
    2. What breaks if inputs are empty, invalid, or malicious?
    3. Which callers or consumers depend on this behavior?
    4. Does this change alter any public or implicit contract?
    5. Are there silent failure modes that will go unnoticed?
    6. Is the code more complex than it needs to be?
    7. Would a new engineer understand and safely modify this at 3 AM?
    8. Are concurrency, ordering, or timing assumptions safe?
    9. Are security and privacy concerns addressed (data exposure, auth, logging)?
    10. Are there performance or resource regressions?
    11. Is any documentation or runbook update required?
</expert_reviewer_questions>

<review_checklist>
    <design>
      - Does the change belong here, or should it live in a shared component?
      - Is over-engineering avoided?
    </design>
    <functionality>
      - Are edge cases and error paths handled correctly?
      - Are the inputs and outputs validated where required by conventions?
    </functionality>
    <complexity>
      - Are functions/classes understandable and appropriately sized?
    </complexity>
    <security_privacy>
      - Is untrusted input validated or sanitized?
      - Is sensitive data avoided in logs and error messages?
    </security_privacy>
    <performance_resources>
      - Are timeouts, retries, and cleanup handled where needed?
      - Are resource leaks and main-thread I/O avoided?
    </performance_resources>
    <compatibility>
      - Are migrations, versioned contracts, or feature flags required?
    </compatibility>
</review_checklist>

<comment-format>
Each comment includes: file path, line number(s), severity (Blocker/Major/Minor), and body.

```markdown
#### `src/Billing/Infrastructure/Repository/DbalInvoiceRepository.php`

**L19** (Major)
> `save()` reads back the row it just wrote via a second `SELECT` to get the
> generated ID, instead of using Postgres `RETURNING id` in the original
> `INSERT`. Under concurrent writes this can return the wrong row's ID.

**L42** (Minor)
> This nested if/else can be simplified with a guard clause.
```
</comment-format>

<review-summary-format>
```markdown
## Review Summary

| Severity | Count |
|----------|-------|
| Blocker  | 0     |
| Major    | 4     |
| Minor    | 7     |
```
</review-summary-format>

</skill>