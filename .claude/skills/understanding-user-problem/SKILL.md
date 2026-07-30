---
name: Understanding User Problem
description: Clarify an ambiguous request through structured triage questions before writing requirements. Use when the user problem is unclear, scope is fuzzy, constraints are missing, or multiple interpretations are possible.
---

ALWAYS prepend to your messages "following Understanding User Problem skill..."

<understanding-user-problem>

Use this skill to extract the real problem before attempting specification or implementation.

**Never write code. Never jump into architecture or tools too early.**

<goal>
Produce a validated understanding of:

- the user or stakeholder pain
- the desired outcome
- scope boundaries
- hard constraints
- missing information that still blocks specification
</goal>

<triage-mindset>
Operate like a senior engineer during intake:

- conclusion-oriented, not brainstorming-oriented
- focused on ambiguity removal
- explicit about unknowns and assumptions
- minimal but high-value questions

Do not ask generic checklists blindly. Ask only what is currently unanswered.
</triage-mindset>

<triage-flow>
<subsection name="Phase 1 — Problem framing">
Start with:

1. Who is affected?
2. What pain are they experiencing?
3. What outcome do they want?
4. Why now?

If these are unclear, do not move forward.
</subsection>

<subsection name="Phase 2 — Clarifying interrogation">
Ask at most **3 focused questions per message**.

Prioritize in this order:

1. **Meaning ambiguity** (same words, different interpretations)
2. **Scope ambiguity** (what is in/out)
3. **Constraint ambiguity** (performance, security, compatibility, deadlines)
4. **Validation ambiguity** (how success is measured)
</subsection>

<subsection name="Phase 3 — Assumption surfacing">
After each answer batch, state assumptions explicitly:

- "I am assuming X."
- "If X is wrong, this changes Y."

Ask for confirmation or correction before continuing.
</subsection>

<subsection name="Phase 4 — Understanding checkpoint">
When ambiguity is low, emit a short checkpoint:

```markdown
## Understanding Checkpoint
- Problem: <1-2 lines>
- Desired outcome: <1-2 lines>
- In scope: <bullets>
- Out of scope: <bullets>
- Constraints: <bullets>
- Unknowns: <bullets>
```

Ask: "Is this an accurate understanding?"  
Only proceed to specification once confirmed.
</subsection>

</triage-flow>

<question-bank-use-selectively>
<subsection name="Problem and impact">
- What is failing today, concretely?
- Who is impacted first, and how often?
- What happens if this is not solved this cycle?
</subsection>

<subsection name="Scope and boundaries">
- What must be included in v1?
- What is explicitly out of scope?
- Is there a minimal acceptable version?
</subsection>

<subsection name="Constraints and risks">
- Are there non-negotiable constraints?
- Which existing contracts cannot break?
- Are there legal/compliance/security limitations?
</subsection>

<subsection name="Success and validation">
- What evidence will prove this solved the problem?
- Which scenario would make you say "this is done"?
- What failure case must be handled from day one?
</subsection>

</question-bank-use-selectively>

<anti-patterns>
NEVER:

- propose implementation while the problem is still unclear
- ask 5+ questions in one message
- accept vague goals like "make it better" without metrics or examples
- confuse user requests with user needs
- close discovery without a validated understanding checkpoint
</anti-patterns>

<exit-criteria>
You can hand off to `defining-requirements` only when:

- [ ] problem statement is concrete and shared
- [ ] target outcome is explicit
- [ ] in-scope and out-of-scope are clear
- [ ] critical constraints are known
- [ ] success signal is defined
- [ ] remaining unknowns are listed and prioritized
</exit-criteria>

</skill>
