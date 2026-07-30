---
name: Defining Requirements
description: Convert a clarified problem into a complete, implementation-ready requirement specification. Use when the problem is already understood and you need a clear spec with scope, constraints, scenarios, and acceptance criteria before coding.
---

ALWAYS prepend to your messages "following Defining Requirements skill..."

<defining-requirements>

Transform the current understanding of the user problem into a precise, reviewable specification that implementation teams can execute without ambiguity.

**Never write code. Never start implementation from this skill.**

<intent>
This skill is for **specification quality**, not discovery.  
If core ambiguity still exists, use `understanding-user-problem` first.
</intent>

<specification-quality-standard>
A valid specification must be:

- **Problem-first**: clearly states the user/business problem before any solution detail.
- **Testable**: acceptance criteria can be validated objectively.
- **Bounded**: in-scope and out-of-scope are explicit.
- **Constraint-aware**: performance, security, compatibility, and operational constraints are documented.
- **Decision-ready**: unresolved questions are explicit and prioritized.
</specification-quality-standard>

<protocol>
<subsection name="Step 1 — Confirm understanding baseline">
Start by restating the problem and scope in 3-6 bullets.  
Ask for confirmation: "Is this accurate before I formalize the spec?"

Do not continue if the baseline is wrong.
</subsection>

<subsection name="Step 2 — Build the requirements model">
Structure the spec around these sections:

1. **Problem Statement**
2. **Goals and Non-Goals**
3. **Functional Behavior**
4. **Inputs and Outputs**
5. **Scenarios (happy path + edge/error paths)**
6. **Constraints and Quality Attributes**
7. **Acceptance Criteria**
8. **Risks, Assumptions, and Open Questions**
</subsection>

<subsection name="Step 3 — Apply trade-off clarity">
When relevant, capture at least one meaningful trade-off:

- What this decision optimizes
- What this decision sacrifices
- Why this trade-off is acceptable now
</subsection>

<subsection name="Step 4 — Add verification plan">
Define how completion will be verified:

- observable outcomes
- acceptance checks
- key failure scenarios that must pass
</subsection>

</protocol>

<output-template>
```markdown
## Spec: <feature name>

### Problem
<Who has the problem, what pain exists today, why it matters now>

### Goals
- <Goal 1>
- <Goal 2>

### Non-Goals
- <Explicitly excluded item>

### Behavior
<Precise behavior bullets. No implementation details.>

### Inputs
| Input | Source | Format | Required? |
|-------|--------|--------|-----------|
| ...   | ...    | ...    | ...       |

### Outputs
| Output | Consumer | Format |
|--------|----------|--------|
| ...    | ...      | ...    |

### Scenarios

#### Happy path
- GIVEN <context>
- WHEN <action>
- THEN <outcome>

#### Edge/Error paths
- GIVEN <boundary or failure condition>
- WHEN <action>
- THEN <expected outcome>

### Acceptance criteria
- [ ] <concrete, testable criterion>
- [ ] <concrete, testable criterion>

### Constraints
- Performance: <if any>
- Security/permissions: <if any>
- Compatibility/contracts: <if any>

### Out of scope
- <what is explicitly excluded from this version>

### Trade-offs
- Chosen: <decision>
- Benefit: <what improves>
- Cost: <what becomes harder>

### Risks and assumptions
- Risk: <risk + mitigation>
- Assumption: <assumption that must hold>

### Open questions
- P0 (blocks implementation): <question>
- P1 (can be decided during implementation): <question>
```
</output-template>

<guardrails>
- Keep the document concise, explicit, and execution-oriented.
- Separate facts from assumptions.
- No solutioning beyond what is required to define behavior and constraints.
- If critical ambiguity remains, stop and switch to `understanding-user-problem`.
- A spec with explicit open questions is acceptable; a spec with hidden ambiguity is not.
</guardrails>

</skill>
