---
name: next-mvp-task
description: >-
  Recomana la propera tasca per avançar l'MVP (o un refactor que desbloquegi
  velocitat), contrastant el tauler de Notion, CLAUDE.md, product-context i
  l'estat real del codi. Triggers: "quina és la propera tasca", "què fem
  després", "next MVP task", "prioritza el backlog", "què refactoritzar".
argument-hint: "[opcional: focus backend|frontend|producte|refactor]"
allowed-tools: Bash, Read, Grep, Glob, Agent, AskUserQuestion, Skill
---

# next-mvp-task

Decideix **una** propera acció (feature MVP o refactor de millora) amb evidència.
No implementa. No crea tasques a Notion tret que l'usuària ho demani després.

## Board (per defecte)

- Database: [Eina KALs — Tauler de tasques](https://app.notion.com/p/434c31c0bbe34e58b3c3cfcbc6c1af57)
- Data source: `collection://2596b7d1-8fb7-4817-8657-a98322a6d3c5`
- Propietats: `Tasca`, `Estat` (`Per fer` | `En curs` | `Testing` | `Fet`), `Fase`, `Prioritat`, `Àrea`

Si el fetch/query falla, `notion-search` amb «Eina KALs Tauler de tasques» i re-resol.

## Camí crític MVP (ordre de valor)

Font: `CLAUDE.md` «Funcionalitats MVP» + skill `product-context`.

1. Organitzadora gestiona el KAL (CRUD complet: create/get **i** patch/delete + pistes)
2. Auth + invitació + join (<30 s mòbil)
3. UI mínima per validar el flux (frontend)
4. Fotos per pista + galeria (moment estrella mòbil)
5. Fitxa PatternInfo (progressiva)
6. Reunions (enllaç+horari) si encara no són usables end-to-end
7. Recordatoris email
8. Moderació (amagar fotos / expulsar)
9. Mètriques de validació
10. **Candidata** (no assumir): xat d'aula — només si les entrevistes ho confirmen

Fora de l'MVP (no recomanar com a «propera» tret que bloquegi): gamificació, pagaments, Pattern BC separat, multi-aula per idioma, IA, etc.

## Workflow

### 1. Orient

Llegeix (en paral·lel quan es pugui):

- `CLAUDE.md` — MVP / fora MVP / arquitectura
- `.claude/skills/product-context/SKILL.md` — fases i negoci
- `$1` si l'usuària ha demanat focus (`backend`, `frontend`, `producte`, `refactor`)

### 2. Tauler Notion

Via Notion MCP (`notion-query-data-sources` SQL contra el data source de dalt):

```sql
SELECT "Tasca", "Estat", "Prioritat", "Àrea", url
FROM "collection://2596b7d1-8fb7-4817-8657-a98322a6d3c5"
WHERE "Fase" = 'MVP' AND "Estat" != 'Fet'
ORDER BY
  CASE "Estat" WHEN 'En curs' THEN 0 WHEN 'Testing' THEN 1 WHEN 'Per fer' THEN 2 ELSE 3 END,
  CASE "Prioritat" WHEN 'Alta' THEN 0 WHEN 'Mitjana' THEN 1 ELSE 2 END
```

Per a candidates *En curs* / Alta: `notion-fetch` la pàgina i llegeix el cos (sovint diu què queda pendent).

### 3. Estat del codi (no et fies només del tauler)

Comprova amb `Glob` / `Grep` / lectura puntual:

| Capacitats MVP | On mirar (pistes) |
|----------------|-------------------|
| Kal create/get | `src/Kal/UI/Http/`, handlers Create/Get |
| Kal patch/delete | absència de controllers/commands Update/Delete |
| Join / invite | `Participation*`, `JoinPolicy` |
| Fotos | `Photo`, buckets, specs; avui sovint **absent** al backend |
| PatternInfo | domini/taules `pattern_*` |
| Recordatoris | Messenger/Scheduler/mailer usage |
| Moderació | hide photo / expel participation |
| Mètriques | queries/endpoints de reporting |
| Frontend | repo frontend o nota al tauler; aquest backend sol |

Anota **gaps** (tasca Notion diu X, codi només té Y) i **deute** visible al diff recent / `docs/specs/` vs implementació.

### 4. Candidates de refactor (sempre avaluar)

Un refactor només surt com a «propera» si **desbloqueja** el camí crític o evita un silenci/ regresió cara. Exemples típics aquí:

- Errors de servidor encara com a `DomainException` plain (haurien de ser `CorruptedStateException`) → 400 silenciosos
- Contracte HTTP incomplet d'un agregat ja a mig camí (create sense patch/delete)
- Ports/`@throws` desalineats amb l'adaptador
- Specs (`docs/specs/`) que menteixen sobre el codi actual
- Migracions apilades innecessàries **només** si encara no hi ha prod i l'usuària accepta `db reset` (no proposar reescriure historial a cegues)
- Tests d'integració que afirmen el bug (ex. unique → `already_exists` genèric)

No proposis «neteges cosmètiques» ni multi-aula / sharding com a refactor d'MVP.

### 5. Puntuació (tria'n una)

Per a cada candidata (tasca Notion o refactor ad-hoc), puntua 0–2 a:

| Criteri | 2 | 1 | 0 |
|---------|---|---|---|
| Camí crític | Desbloqueja el pas següent del loop | Ajuda però no és el coll d'ampolla | Lateral / Fase 2+ |
| Ja *En curs* | Tancar-la evita context-switch | — | Nova en paral·lel amb una *En curs* |
| Evidència al codi | Gap clar i acotat | Parcial | Només al tauler, sense rastre |
| Esforç vs valor | Petit/mitjà, valor alt | Gran però necessari | Gran i ajornable |
| Risc de no fer-ho | Regressió silenciosa / MVP inviable | Friction | Baix |

**Regles de desempat:**

1. Preferir **tancar** una *En curs* Alta abans d'obrir-ne una de nova.
2. Feature de camí crític &gt; refactor, tret que el refactor elimini un silenci 4xx/5xx o un contracte mentider.
3. Candidata xat → **no** recomanar sense evidència d'entrevistes / decisió explícita.
4. Frontend vs backend: si el backend del pas actual ja permet el flux i la UI és buida, la UI pot ser la propera *tot i* ser un altre repo — digues-ho.

### 6. Sortida (idioma de l'usuària; català per defecte)

```markdown
## Proper pas

**Recomanació:** <títol> — <feature | refactor>
**Per què ara:** <2–4 frases, amb evidència codi + Notion>
**Fet quan:** <criteri d'acceptació curt>
**Enllaç:** <URL Notion si n'hi ha; si és refactor ad-hoc, proposa crear tasca>

### Runners-up
1. … — <per què no ara>
2. …

### Refactors apreciats (no són el proper pas tret que guanyin)
- … — esforç/impacte

### Fora d'abast conscient
- … (Fase 2 / candidata / tràmit)

### Següent moviment
- Si acceptes: `/start-task` amb l'URL o el títol
- Si vols tasca nova al tauler (refactor): `notion-creating-task`
```

## Notes

- **Proposar, no decidir en silenci:** l'arquitecta confirma.
- No implementis ni obris PR des d'aquesta skill.
- No inventis estat de tasques: si Notion no respon, digues-ho i basa't només en codi + CLAUDE.md.
- Mantén la resposta curta: una recomanació forta &gt; llista llarga.
