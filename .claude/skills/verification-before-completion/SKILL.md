---
name: Verification Before Completion
description: Evidence-first completion protocol. Use when you are about to claim "done", "fixed", "green", or "ready" for any implementation, review, or QA step.
---

ALWAYS prepend to your messages "following Verification Before Completion skill..."

<verification-before-completion>

<core-rule>
NO COMPLETION CLAIMS WITHOUT FRESH VERIFICATION EVIDENCE.

Confidence is not evidence. A successful change is only complete after running the command that proves the claim and checking its output.
</core-rule>

<always>
- ALWAYS identify the exact command that proves each claim.
- ALWAYS run that command after the latest change (fresh run).
- ALWAYS inspect exit code and key output lines.
- ALWAYS report the real status, even when it fails.
- ALWAYS include remaining blockers when verification is incomplete.
</always>

<never>
- NEVER say "done", "fixed", "passes", or "ready" without running proof commands.
- NEVER rely on old outputs from previous runs.
- NEVER hide failing checks behind partial success.
- NEVER skip verification because the change "looks small".
</never>

<verification-gate>
Before any completion statement, execute this gate:

1. **Claim**: Write the exact claim (`tests pass`, `lint clean`, `bug fixed`).
2. **Proof command**: Choose the command that directly validates it.
3. **Run**: Execute now, against current code.
4. **Read**: Confirm exit status and expected output.
5. **Decide**:
   - If proven: claim with evidence.
   - If not proven: report actual state and next action.
</verification-gate>

<common-claim-proof-mapping>
- `Bug fixed` -> Reproduce failing scenario and confirm it now passes.
- `Tests pass` -> Run affected tests (and broader suite when needed).
- `Build passes` -> Run build command.
- `Type-check clean` -> Run type checker.
- `Ready for PR` -> Run required project checks and summarize.
</common-claim-proof-mapping>

<output-format>
Use this structure when closing work:

```markdown
Verification summary:
- Claim: <what was being validated>
- Command: `<exact command>`
- Result: PASS | FAIL
- Evidence: <key output lines or metrics>

Status:
- <done or blocked, with concrete next action>
```

If either fails, do not claim completion. Report failure and continue with fixes.
</output-format>

<checklist>
- [ ] Every completion claim has a matching proof command
- [ ] Proof commands were run after latest edits
- [ ] Output and exit codes were checked
- [ ] Completion statement reflects real results
- [ ] Remaining failures/blockers are explicit
</checklist>

</skill>
