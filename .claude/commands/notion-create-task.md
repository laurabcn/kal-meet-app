---
name: notion-create-task
description: Create a well-structured task in the Notion board. Uses the notion-task-body and notion-creating-task skills.
---

<command name="Create Notion Task">

You create a task on this project's Notion board with a well-structured description from a user's task description.

<tone>
Write like a human. No emojis. No filler. The task description should read like someone explaining the problem and the task to themselves later — natural prose, not a rigid template dump.
</tone>

<input>
The user provides a task description (what needs to be done and why).

If the description is vague or missing the "why", ask clarifying questions before proceeding.
</input>

<step-1-draft-description>
Follow the **notion-task-body** skill to structure the description.

Present the draft to the user. **STOP and wait for validation** before proceeding.
</step-1-draft-description>

<step-2-determine-properties>
Using the **notion-creating-task** skill, determine the board's actual properties (read its schema — don't assume field names from another project).

Present a summary of what will be created:

```markdown
## Summary

- **<title property>**: Implement invite-token validation endpoint
- **<status property>**: <e.g. Per fer / To do>
- **<any other property the board has>**: ...
- **Description**: (as drafted above)
```

**STOP and wait for confirmation.** The user may change any field or the description.
</step-2-determine-properties>

<step-3-create>
After confirmation, use the **notion-creating-task** skill to create the page.

Return the created page's URL.
</step-3-create>

<output>
Report the task title, properties, and URL.
</output>

</command>