---
name: Notion Creating Task
description: Create a task/ticket on a Notion board via the Notion MCP tools. Use when creating a new task/ticket for this project.
---

ALWAYS prepend to your messages "following Notion Creating Task skill..."

<notion-creating-task>

<prerequisites>
- Notion MCP connected (`notion` in `claude mcp list`)
- Apply the `notion-task-body` skill for the page content structure
</prerequisites>

<board>
This repo has no fixed task board wired in yet. Before creating a page:

1. If the user already named a board this session, reuse it — don't ask again.
2. Otherwise, ask the user which board/database to use, or `notion-search` for
   a name they give you.
3. Once resolved, fetch its schema with `notion-fetch` (don't assume it from
   memory or from another project) — read the actual property names and
   select options before filling anything in.

Don't assume Jira-style fields (ticket key, epic/sub-task hierarchy). Most
Notion task boards are flat lists with a title + a handful of select
properties (status, priority, area) and the description as page content
rather than a property — but confirm against the real schema, don't guess.
</board>

<creating-a-task>
Use `notion-create-pages` with `parent: {"type": "data_source_id", "data_source_id": "<resolved id>"}`.

```json
{
  "pages": [{
    "properties": {
      "<title property>": "Implement invite-token validation endpoint",
      "<status property>": "<the board's \"not started\" option>"
    },
    "content": "## What\n...\n\n## Why\n...\n\n## Expected output\n- ..."
  }]
}
```

Defaults when not specified by the user:
- Status: whatever option the board uses for "not started yet" (e.g. `Per
  fer`, `To do`, `Backlog`) — read it from the schema, don't guess a label.
- Any other required select property: ask rather than guessing — these
  usually reflect product decisions, not something to infer.
</creating-a-task>

<error-handling>
| Error | Resolution |
|-------|------------|
| Property value not in the select's options | Don't invent a new option value silently — ask the user, since adding an option changes the shared board's schema |
| Can't find the data source | Re-run `notion-search` with the board name; the ID may be stale if the board was recreated |
</error-handling>

</skill>
