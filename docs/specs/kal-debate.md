# Spec: Kal Debate (classroom chat)

## Naming (EN)

| Català (producte) | Anglès (codi / spec) | Notes |
|-------------------|----------------------|--------|
| Debat / aula de xat | **Debate** | Taules: `debate_rooms`, `debate_messages` |
| Aula | Debate room / classroom chat | 1 room per KAL a l’MVP |
| Xat en directe (preguntes) | Same debate room | No hi ha un xat separat de la Meeting |

En codi i specs posteriors: preferir **Debate**. A la UI (i18n): “Aula” / “Chat”.

## Problem

Un KAL sense conversa interna obliga a Telegram/Instagram: es perden dades,
no es pot moderar dins el producte, i no hi haurà corpus per IA (fase 2).
L’organitzadora i les participants necessiten un **xat propi in-app**,
també durant la trobada en directe (preguntes mentre es mira l’embed),
sense construir un servidor de sockets ni allotjar vídeo.

## Goals

- 1 debate room per KAL, només membres.
- Missatges en **text** i **imatges** (Storage), historial + entrega en viu
  (Supabase Realtime).
- Moderació mínima: organitzadora amaga missatges (`hidden`).
- La **Meeting** (URL YouTube/Zoom/Meet) + el **mateix debate** a la
  mateixa pantalla = canal de preguntes en directe.
- Dades a Postgres (preparat per resums IA més endavant).

## Non-Goals

- Node / Socket.io / Reverb / API PHP per cada missatge.
- Vídeos pujats (fitxer) al xat; clips curts allotjats.
- Fils (`reply_to`), múltiples aules, xat per pista (`clueId`).
- Reaccions, @mencions, cerca full-text.
- Panell IA / resums (fase 2).
- Modelar `DebateMessage` com a agregat DDD al PHP.

## Behavior

### Arquitectura

- **Lectura/escriptura de missatges:** frontend Vue (TypeScript) →
  Supabase (`supabase-js`) amb JWT d’usuària + RLS.
- **En viu:** Supabase Realtime sobre `debate_messages`.
- **Creació de l’aula:** diferida. Avui `CreateKal` **no** inserta
  `debate_room` (xat candidat MVP, pendent de validació). Quan el xat
  es cablegi: insert al CreateKal + backfill dels KALs existents.
  Veure [`kal-aggregate-mvp.md`](kal-aggregate-mvp.md).
- **Imatges de missatge:** upload al bucket privat (p.ex. `kal-photos`,
  path tipus `{kal_id}/debat/{message_id}.webp`), compressió al client;
  el missatge guarda `image_path` (o equivalent).

### Producte

- Només participen usuàries membres del KAL (organitzadora inclosa via
  `is_kal_member` / organizer).
- Durant la trobada: embed o botó “Obrir Zoom” + debate a la mateixa
  vista; les preguntes van al debate de l’app (no cal el xat natiu de
  YouTube/Zoom).
- Enllaços URL (p.ex. YouTube) poden anar al `body` com a text; no cal
  tipus de missatge “video” al MVP.
- El debate és **sempre a nivell KAL**, mai per pista.

## Inputs

| Input | Source | Format | Required? |
|-------|--------|--------|-----------|
| room_id | FE (resolt via kal) | ULID | yes |
| author_id | Sessió / profile id intern | ULID | yes |
| body | Usuària | text ≤ ~2000 chars | no si hi ha imatge |
| image | Usuària | fitxer → storage path | no si hi ha body |
| hidden | Organitzadora | bool | només update |

Almenys un de `body` / `image_path` no buit.

## Outputs

| Output | Consumer | Format |
|--------|----------|--------|
| Llista de missatges | FE | files ordenades per `created_at` |
| Event Realtime | FE | INSERT/UPDATE de missatge |
| Missatge amagat | Membres | deixa de veure’s (org pot continuar veient-lo) |

## Scenarios

### Happy path

- GIVEN dues membres del mateix KAL  
  WHEN A envia text  
  THEN B el veu sense refrescar (Realtime).

- GIVEN una membre  
  WHEN puja una imatge al debate  
  THEN el missatge mostra la imatge (URL signada / path resolt al FE).

- GIVEN una trobada amb URL de directe i el debate obert  
  WHEN una participant escriu una pregunta  
  THEN el missatge apareix a l’aula; l’organitzadora pot respondre en veu
  o per escrit.

- GIVEN l’organitzadora  
  WHEN marca `hidden = true`  
  THEN la resta de membres no veuen el missatge.

### Edge / Error paths

- GIVEN usuària no membre  
  WHEN SELECT/INSERT  
  THEN RLS ho denega.

- GIVEN missatge sense body ni imatge  
  WHEN INSERT  
  THEN rebuig (check constraint o validació FE + DB).

- GIVEN Realtime caigut  
  WHEN la usuària torna a obrir l’aula / refetch  
  THEN veu l’historial via SELECT (degradació acceptable).

## Acceptance criteria

- [ ] Migració: `debate_rooms`, `debate_messages` (+ RLS + publicació
      Realtime).
- [ ] CreateKal deixa exactament 1 room per KAL.
- [ ] Membres poden enviar text i imatges; no-membres no.
- [ ] Historial carregable (paginació cap enrere acceptable).
- [ ] Entrega en viu via Realtime (o refetch documentat com a fallback).
- [ ] Organitzadora pot amagar; membres no veuen hidden.
- [ ] Cap endpoint Symfony de create/list message al MVP.
- [ ] Documentat a `CLAUDE.md`: debate deixa de ser “candidat pendent
      d’entrevistes”; abast = 1 aula, text+imatges, mateix canal per al
      directe.

## Constraints

- Performance: volum MVP família / pocs KALs; sense fan-out complex.
- Security: RLS amb `is_kal_member` / `is_kal_organizer`; bucket privat;
  URLs signades per imatges.
- Compatibility: ULIDs app-side; `author_id` = `profiles.id` intern, no
  `auth.uid()` directe.
- i18n UI: ca/es; sense copy hardcoded al FE.

## Out of scope

- Vídeo allotjat al debate o a Storage com a clip de xat.
- Fils, múltiples rooms, debate per pista.
- Expulsar (Participation).
- Integració Telegram.

## Trade-offs

- Chosen: Supabase directe per missatges; PHP només crea la room.
- Benefit: Zero Node, mateix patró que fotos/auth, dades pròpies per IA.
- Cost: Regles de negoci del xat viuen a RLS + FE; menys “tot al DDD PHP”.
- Acceptable ara: MVP mitja jornada; extensible després (més rooms, fils)
  sense canviar de stack.

## Risks and assumptions

- Assumption: Auth magic link + `profiles` + helpers RLS de membre ja
  disponibles abans del FE del debate.
- Assumption: Participation/join existeix abans de provar el xat amb dues
  usuàries.
- Risk: Policies RLS mal escrites → fuites entre KALs; mitigació: tests
  SQL/RLS i advisors Supabase.
- Risk: Confondre embed del directe amb “vídeo al xat”; mitigació: Meeting
  = URL; debate = text/imatge (± URL en text).

## Open questions

- P0: Cap (producte tancat a la discussió).
- P1: Les imatges del debate entren també a la galeria del KAL
  automàticament, o només viuen al missatge?
- P1: Límite exacte de mida/compressió d’imatge al debate (reutilitzar
  ~300 KB de fotos?).

## Verification

- Dues sessions membre: text + imatge en viu.
- Tercera sessió no membre: sense lectura.
- Hide: missatge desapareix per membres.
- Quan el xat es cablegi: CreateKal assert 1 `debate_room` + backfill
  dels KALs creats sense aula.
- Prova manual “directe”: embed/enllaç + pregunta al debate a la mateixa
  pantalla.

## Relació amb altres specs

- Prerequisit agregat / CreateKal: [`kal-aggregate-mvp.md`](kal-aggregate-mvp.md)
- Domain-only històric: [`kal-aggregate-root.md`](kal-aggregate-root.md)
  (no inclou debate al Domain PHP — correcte)
