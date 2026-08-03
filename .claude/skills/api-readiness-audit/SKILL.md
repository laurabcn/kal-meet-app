---
name: api-readiness-audit
description: >-
  Pre-endpoint readiness audit — finds gaps (authz, read/write ports, contracts,
  observability, tests, ops) before adding more HTTP/API surface. Use when the
  user asks what is missing before more endpoints, whether the API is ready to
  grow, for a backend readiness review, or invokes api-readiness-audit.
disable-model-invocation: true
---

# API Readiness Audit

Stack-agnostic audit of a codebase **before** adding more HTTP/API endpoints.
Evidence-based only; do not implement changes unless the user asks afterward.

ALWAYS prepend messages with: `following api-readiness-audit skill...`

## Goal

Answer: *What must we close (or decide) before more endpoints, what can wait, and what is already fine?*

Optional focus from the user (e.g. “before GET /resource/{id}”). If none, audit against the next obvious read/write surface implied by specs or existing routes.

## Workflow

### 1. Orient

Identify in parallel:

- Stack and entrypoints (framework, language, `composer.json` / `package.json` / `go.mod` / etc.)
- Existing HTTP routes / controllers / handlers
- Auth mechanism (JWT, session, API keys, none)
- Persistence ports (repos, ORMs, SQL) — especially **read** vs **write**
- Specs / ADRs / `CLAUDE.md` / README claiming Deferred / out of scope
- Quality gates (`make qa`, CI workflows, test layouts)
- Observability (logs, error mapping, alerts, health)

Prefer repo docs and code over assumptions. Cite **file paths** (and line ranges when useful).

### 2. Score each lens

For every lens below, label each finding:

| Label | Meaning |
|-------|---------|
| **Missing** | Blocks or strongly shapes the next endpoint(s) |
| **Thin** | Exists but incomplete / untested / easy to get wrong |
| **Fine for later** | Documented deferral or not needed for the next slice |

#### Lenses (adapt to stack; skip N/A)

1. **Contract surface** — OpenAPI/schema, versioning, CORS, content types, success/error shapes, create-response vs read path (ids, tokens clients need next)
2. **Authn / authz** — Identity verification vs **resource** authorization (owner/member/roles). Note if DB RLS exists but the API bypasses it (service role / admin client)
3. **Persistence & domain ports** — create-only repos, missing `findById` / reconstruction, soft-delete filters, transactions, schema vs domain drift
4. **Query / read stack** — handlers, DTOs/response types, pagination/metadata conventions
5. **Error mapping** — stable error codes, not-found → 404, conflict → 409, uncaught → 5xx; client-safe bodies
6. **Observability** — structured logs, request/correlation ids, alerts on 5xx, health vs readiness
7. **Tests & gates** — unit vs integration vs HTTP; whether DB/real infra runs in the default gate/CI; doubles that can drift from SQL
8. **Ops / DX** — CI mirrors local?, secrets only in local env, make/scripts, shallow health
9. **Product/spec deferrals** — Deferred items that become **silent risks** if more endpoints ship without them (e.g. invite token never returned; member checks stubbed)

### 3. Prioritize

Collapse findings into:

- **P0** — Close or decide **before** (or as first slice of) the next endpoint
- **P1** — Soon; parallel OK but do not ignore while the surface grows
- **P2** — Fine for later (say why)

End with a short **recommended order** (3–7 steps) and **open decisions** the human must answer (yes/no or choose A/B). Do not invent product decisions — surface them.

## Output format

```markdown
# API readiness audit — <repo or path>

**Focus:** <next endpoint / “general growth”>
**Stack (detected):** <one line>

## Verdict
<2–4 sentences: ready or not, and the single biggest blocker>

## P0 — Before more endpoints
| Item | Label | Why it matters | Evidence |
|------|-------|----------------|----------|
| … | Missing/Thin | … | `path` |

## P1 — Soon
| … |

## P2 — Fine for later
| … |

## Open decisions
1. …?

## Recommended order
1. …
```

Keep the report pointed. Prefer tables over essays. No generic advice without a path or explicit “not found in repo”.

## Rules

- **Read-only** unless the user then asks to implement.
- Do **not** treat “missing OpenAPI” or “no request ids” as P0 unless the next consumer (FE codegen, multi-client) already depends on them — usually P1/P2.
- Do **treat as P0** gaps that cause **wrong data exposure**, **unreadable aggregates**, or **blocked next product step** (e.g. token generated but never returned).
- If the repo has almost no API yet, say so and shrink the audit; do not pad with framework best-practice lists.
- Match the user’s language for the verdict (Catalan/Spanish/English) when they wrote in that language; keep tables and labels in English if that matches other skills in the workspace, otherwise follow the user.

## Examples

**User:** “Abans de més endpoints, què hi falta?”  
→ Run full audit, focus = next implied route from specs.

**User:** “api-readiness-audit before GET /kal/{id}”  
→ Same lenses, prioritize read port, authz for that resource, not-found mapping, field visibility.

**User:** “Is this Nest API ready to add billing webhooks?”  
→ Emphasize idempotency, auth of webhook sender, observability; de-emphasize OpenAPI if internal-only.
