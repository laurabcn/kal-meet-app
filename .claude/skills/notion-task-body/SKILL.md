---
name: Notion Task Body
description: Guidelines for writing task descriptions on a Notion board. Concise, clear motivation, explicit expected output. Use when drafting or reviewing a task's content.
---

ALWAYS prepend to your messages "following Notion Task Body skill..."

<notion-task-body>

How to write effective task descriptions on a Notion task board. Apply when creating or editing a task's page content.

<principles>
1. **Concise** — No filler. Every sentence adds information.
2. **Clear motivation** — Why this work matters (product, technical, or business context).
3. **Explicit expected output** — What "done" looks like; how to verify completion.
</principles>

<required-sections>

<subsection name="What (Required)">
Clear, specific statement of the exact action or objective. One or two sentences.

```
## What
Implement the invite-token validation endpoint.
```

**Avoid**: Vague goals ("Improve the system"). **Prefer**: Concrete deliverables ("Add POST /invites/validate endpoint with domain validation").
</subsection>

<subsection name="Why (Required)">
Product, technical, or business context explaining the motivation. Answer: why are we doing this?

```
## Why
Partners need to validate invite tokens server-side instead of trusting the
client, per the auth hardening item.
```

**Avoid**: Restating the What. **Prefer**: Stakeholder benefit, constraint, or driver.
</subsection>

<subsection name="Expected Output (Required)">
What does "done" look like? How do we verify it?

```
## Expected output
- POST /invites/validate returns 200 for a valid token, 422 for an expired/malformed one
- Tests cover valid, expired, and malformed tokens
```

**Avoid**: "Working as expected." **Prefer**: Observable outcomes, test criteria, artifacts.
</subsection>

</required-sections>

<optional-sections>
**How** — High-level approach, only if the user provided it.

**Resources** — URLs, documents, or references — only when provided.
</optional-sections>

<verification-rubric>
Before considering a task body complete, verify:

| Section | Requirement | Sufficient? |
|---------|-------------|-------------|
| **What** | Action or goal is clear and specific | "Add POST /invites/validate endpoint" |
| **Why** | Motivation or purpose is explained | "Partners need server-side validation instead of trusting the client" |
| **Expected output** | Done criteria are testable/observable | "Endpoint returns 200/422 correctly; tests pass" |

**Decision flow:**
1. All three present and clear? → Proceed
2. One or more missing/unclear? → Ask clarifying questions
3. After clarification → Re-verify before proceeding
</verification-rubric>

<clarifying-question-templates>
- Missing What: "What specific action or deliverable should this task accomplish?"
- Missing Why: "What's the product or technical reason for this work?"
- Missing Expected output: "How will we know this is done? What should be true afterwards?"
</clarifying-question-templates>

<anti-patterns>
- **Wall of text** — Break into sections. Use bullets for lists.
- **Jargon without context** — Define acronyms or link to docs.
- **Implied scope** — State boundaries explicitly ("Out of scope: photo uploads").
- **No verification** — "Expected output" must be checkable.
</anti-patterns>

</skill>
