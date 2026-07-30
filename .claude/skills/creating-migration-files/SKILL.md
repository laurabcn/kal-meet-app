---
name: Managing Migrations
description: Migration lifecycle via Makefile — create, apply, rollback, check status. Use when creating, running, or reverting database migrations. NEVER create migration files manually.
---

ALWAYS prepend to your messages "following Managing Migrations skill..."

<creating-migration-files>

<critical-never-create-migration-files-manually>
NEVER write or create migration files by hand. ALWAYS use the project's Makefile targets to generate, apply, and revert migrations. The Makefile encapsulates the correct ORM commands, environment setup, and naming conventions.
</critical-never-create-migration-files-manually>

<workflow>
<subsection name="1. Discover available targets">
Before doing anything, find the migration-related Make targets:

```bash
grep -iE 'migrat|makemigrat|showmigrat' Makefile
```

Look for targets like `make migrations`, `make makemigrations`, `make migrate`, `make showmigrations`, `make migrate-down`, `make migrate-rollback`, or similar.
</subsection>

<subsection name="2. Create a migration">
ALWAYS use the Make target to auto-generate the migration from model changes:

```bash
make makemigrations       # or whatever the project's target is
```

If the target accepts arguments (app name, migration name), pass them as documented in the Makefile.

NEVER run the ORM migration command directly (e.g., `python manage.py makemigrations`, `alembic revision`). The Makefile may wrap it with required env vars, Docker exec, or pre/post hooks.
</subsection>

<subsection name="3. Check migration status">
```bash
make showmigrations       # or equivalent
```
</subsection>

<subsection name="4. Apply migrations">
```bash
make migrate              # or equivalent
```
</subsection>

<subsection name="5. Rollback / revert">
```bash
make migrate-down         # or equivalent
```

If no rollback target exists, ask the user how rollbacks are handled in this project before proceeding.
</subsection>

</workflow>

<rules>
- NEVER create a `.py`, `.sql`, or any migration file with the Write tool or by hand.
- NEVER run raw ORM commands (`manage.py`, `alembic`, `knex`, `prisma`) directly — always go through Make.
- If the Makefile has no migration targets, STOP and ask the user. Do not invent commands.
- After generating a migration, READ the generated file to verify it matches expectations before applying.
- If the generated migration needs manual edits (data migration, RunSQL), edit the generated file — never create from scratch.
</rules>

<checklist>
- [ ] Found the correct Make target for this operation
- [ ] Used `make` to generate the migration (not manual file creation)
- [ ] Read the generated file to verify correctness
- [ ] Migration is backward compatible (old code works with new schema)
</checklist>

</skill>
