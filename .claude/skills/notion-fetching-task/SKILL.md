---
name: Notion Fetching Task
description: Fetches and structures a task/ticket from a Notion board. Use when you need to read task information for classification, planning, or resolution workflows.
---

ALWAYS prepend to your messages "following Notion Fetching Task skill..."

<notion-fetching-task>

Fetch and structure a Notion task's content. Returns a structured brief for orchestrators and workflows.

<prerequisites>
- Notion MCP connected
</prerequisites>

<workflow>

<subsection name="Step 1 — Identify the task">
| Input Format | Example | How to resolve |
|---|---|---|
| Notion page URL | `https://app.notion.com/p/...` or `https://notion.so/...` | Pass directly to `notion-fetch` |
| Page ID (UUID) | `39b00515-7459-813f-8a21-e1f83739bc98` | Pass directly to `notion-fetch` |
| Free-text description | "the invite token task" | Use `notion-search` first, scoped to the board if the user named one, then fetch the best match |

If nothing matches, ask the user for the page or a better description. If the
user hasn't told you which board/database this project tracks tasks in, ask
once and remember it for the rest of the session rather than re-asking.
</subsection>

<subsection name="Step 2 — Fetch the task">
```
notion-fetch(id=<page url or id>)
```

This returns the page properties (whatever the board's schema defines — status,
priority, area, etc.) and the page content (Markdown), which is the task
description unless the board also has a dedicated description property.
</subsection>

<subsection name="Step 3 — Build the structured brief">
```markdown
## Task: <title>

### Problem/Requirement
<Page content, cleaned and formatted>

### Metadata
<One bullet per property the page actually has — status, priority, area,
assignee, etc. Use the property names as they appear on the board; don't
invent a fixed set.>

- **URL**: <page url>
```

Only list properties that exist on this board — don't invent placeholders for
fields (assignee, labels, parent/epic) the board doesn't have.
</subsection>

</workflow>

<error-handling>
| Error | Resolution |
|-------|------------|
| Page not found | Verify the URL/ID; `notion-search` again with different terms |
| Ambiguous free-text match | Present the top 2-3 candidates and ask the user to pick |
</error-handling>

</skill>
