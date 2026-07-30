---
name: Fact Checking
description: Verify AI-generated documentation against source code and architecture for accuracy. Use when reviewing documentation created by AI agents, validating technical claims, verifying architectural descriptions, or ensuring service interactions are correctly documented.
---

ALWAYS prepend to your messages "following Fact Checking skill..."

<verifying-documentation>

<rules>
ALWAYS verify documentation claims by reading the actual source code.

ALWAYS check that function signatures, parameters, and return types match the code.

ALWAYS run code examples to confirm they work as documented.

ALWAYS verify imports and dependencies exist and are correctly referenced.

ALWAYS cross-reference class/method names against the codebase—typos in AI-generated docs are common.

ALWAYS verify architectural claims by checking configuration files, infrastructure code, and service definitions.

ALWAYS trace service connections through actual code (API clients, message queues, database connections).

NEVER assume AI-generated documentation is accurate—always validate against source.

NEVER trust architectural diagrams without verifying the actual service interactions exist.

NEVER accept claims about service communication without checking the implementation.

ALWAYS flag outdated documentation when code or architecture has changed.

ALWAYS note confidence level when systems are too complex to fully verify.
</rules>

<technical-verification>
<subsection name="Process">
1. **Locate the source** - Find the actual file/function being documented
2. **Compare signatures** - Verify function names, parameters, types match exactly
3. **Trace behavior** - Read implementation to confirm documented behavior
4. **Test examples** - Run code snippets to verify they execute correctly
5. **Check dependencies** - Confirm imports, packages, and versions are accurate
</subsection>

<subsection name="Common Technical Errors">
| Category | Examples |
|----------|----------|
| Signature mismatches | Wrong parameter names, missing optional params, incorrect return types |
| Behavioral claims | Describing intended vs actual behavior, missing edge cases |
| Reference errors | Non-existent classes, wrong import paths, hallucinated functions |
| Example issues | Code that doesn't run, missing imports, outdated API patterns |
</subsection>

</technical-verification>

<architectural-verification>
<subsection name="Process">
1. **Identify services** - List all services/components mentioned in documentation
2. **Verify existence** - Confirm each service exists in the codebase or infrastructure
3. **Trace connections** - Find actual code that implements service communication
4. **Check protocols** - Verify communication methods (REST, gRPC, events, queues)
5. **Validate data flows** - Confirm data transformations match documented flows
</subsection>

<subsection name="What to Verify">
| Claim Type | Where to Check |
|------------|----------------|
| Service exists | Deployment configs, docker-compose, k8s manifests |
| Service A calls Service B | API clients, HTTP calls, SDK usage |
| Events/messages | Event publishers, queue producers, subscribers |
| Database connections | ORM configs, connection strings, repository classes |
| External integrations | API clients, SDK initialization, credentials config |
| Data flows | Command handlers, event handlers, domain services |
</subsection>

<subsection name="Common Architectural Errors">
- **Phantom services** - Documenting services that don't exist or were removed
- **Wrong direction** - Service A calls B, documented as B calls A
- **Missing intermediaries** - Direct connection documented but goes through gateway/proxy
- **Outdated protocols** - REST documented but migrated to gRPC or events
- **Assumed patterns** - Documenting expected architecture vs actual implementation
- **Stale diagrams** - Architecture evolved but docs reference old design
</subsection>

<subsection name="Verification Commands">
```bash
# Find service definitions
grep -r "service_name" docker-compose*.yml
grep -r "Deployment" k8s/

# Find API client usage
grep -r "Client\|HttpClient\|requests\." .

# Find event publishers/subscribers
grep -r "publish\|subscribe\|emit\|on_event" .

# Find database connections
grep -r "DATABASE_URL\|connection_string\|engine\|Session" .

# Trace service calls
grep -r "base_url\|endpoint\|api/" .
```
</subsection>

</architectural-verification>

<checklist>
<subsection name="Technical">
- [ ] Located and read the actual source code
- [ ] Verified function/class signatures match documentation
- [ ] Confirmed parameter names, types, and defaults are accurate
- [ ] Tested code examples execute without errors
- [ ] Checked imports and dependencies exist
</subsection>

<subsection name="Architectural">
- [ ] Verified all documented services exist
- [ ] Traced service connections through actual code
- [ ] Confirmed communication protocols are accurate
- [ ] Validated data flow directions match implementation
- [ ] Checked infrastructure configs match documented architecture
</subsection>

<subsection name="General">
- [ ] Flagged any unverifiable claims with confidence level
- [ ] Noted areas where documentation may be outdated
</subsection>

</checklist>

</skill>
