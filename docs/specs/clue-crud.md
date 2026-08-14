# Spec: CRUD de pistes (clues)

> Status: **Draft**
> Escrit 2026-08-14. Completa el CRUD d'organitzadora del context `src/Kal/`:
> avui les pistes només neixen incrustades al `POST /kal` i no es poden ni
> editar ni esborrar per separat. Hereta les decisions de
> [`get-kal.md`](get-kal.md) (envelope `data`, 404 sense filtrar existència) i
> del `CLAUDE.md` § «Dues superfícies d'API» i § «Soft delete: com s'esborra».

---

## Problem

Una pista només es pot crear dins del `POST /kal`, en el mateix cop que el KAL.
Un cop creat, l'organitzadora no pot afegir-ne una de nova, corregir-ne el nom o
les dates, ni retirar-ne cap: `KalRepository::update()` escriu **només** columnes
escalars de `kals` i no toca les taules filles.

Això bloqueja el cas d'ús central de l'eina. Un MKAL es publica per parts: la
pista 2 sovint no existeix quan comença el KAL, i les dates ballen. Avui l'única
sortida és esborrar el KAL i tornar-lo a crear, cosa que li canvia l'`inviteToken`
i deixa fora les participants que ja s'hi havien apuntat.

## Goals / Non-Goals

**Goals**

- L'organitzadora pot afegir, editar i retirar pistes d'un KAL ja creat.
- Els invariants de l'agregat es mantenen a cada escriptura: les dates de la
  pista cauen dins del rang del KAL, i el seu `locale` és un dels habilitats.
- Retirar una pista no deixa la seva reunió òrfena ni visible.
- El contracte d'errors i d'autorització és el mateix que la resta del CRUD:
  organitzadora o 404.

**Non-Goals**

- Llegir pistes per separat: ja surten niades al `GET /kal/{id}`. No hi ha
  `GET /kal/{kalId}/clue/{clueId}`.
- Substituir el PDF d'una pista ni editar-ne la reunió (veure Out of scope).
- Qualsevol cosa de la superfície de participant: l'aula no entra aquí.
- Ordenar pistes a mà. L'ordre es deriva de `startsOn`; no hi ha columna
  `position` i no se n'afegeix cap.

## Behavior

Tres endpoints nous, tots amb JWT obligatori i **exclusius de l'organitzadora**
del KAL. Qui no ho sigui rep `404 kal_not_found`, mai 403: no filtrem ni
l'existència del KAL ni la de la pista.

| Mètode | Ruta | Èxit |
|---|---|---|
| `POST` | `/kal/{kalId}/clue` | `201` + `{"data": {"id": "<ulid>"}}` |
| `PATCH` | `/kal/{kalId}/clue/{clueId}` | `204`, cos buit |
| `DELETE` | `/kal/{kalId}/clue/{clueId}` | `204`, cos buit |

**Camí d'escriptura (els tres iguals).** El handler carrega l'agregat amb
`findById(kalId, organizerId)` — que ja filtra propietat i `deleted_at IS NULL` —
aplica el canvi al domini, i el repositori escriu **només** les files de la
pista i de la seva reunió. Cap escriptura toca `kals`.

- `POST` — el domini genera l'ULID dins de `Clue::create()`, valida el rang
  respecte del KAL via `Kal::addClue()`, i el repositori insereix la fila de
  `clues` i **després** la de `meetings` (la FK `meetings_clue_fk` ho exigeix),
  dins d'una transacció.
- `PATCH` — parcial: només s'apliquen les claus presents. `Clue` és immutable,
  així que el canvi es fa construint una pista nova amb els valors fusionats,
  **conservant `id`, `file` i `meeting`**, i revalidant els invariants abans de
  persistir. Un cos buit (`{}`) és `204` sense escriure res.
- `DELETE` — soft delete: marca `deleted_at` de la pista **i de la seva reunió**,
  a la mateixa transacció. Mai un `DELETE` d'SQL. Un KAL es pot quedar sense cap
  pista: es va decidir que un KAL es pot crear buit, i retirar-les totes és
  el mateix estat.

**Pistes ja alliberades.** Una pista amb `startsOn` passat s'edita i s'esborra
igual que qualsevol altra, sense cap comprovació extra. L'organitzadora mana, i
el cas real més freqüent és corregir una errada just després de publicar. Efecte
acceptat: una participant pot veure canviar una pista que ja estava llegint.

**`updatedAt`.** Un canvi de pista mou `clues.updated_at` i **deixa
`kals.updated_at` com estava**. Cada fila diu quan la van tocar a ella.

## Inputs / Outputs

### `POST /kal/{kalId}/clue`

Mateixa forma que cada element de `clues` al `POST /kal`, perquè el client no
hagi d'aprendre dos formats de la mateixa cosa. `file` i `meeting` són
**obligatoris**: el domini no admet una pista sense PDF ni sense reunió.

```json
{
  "name": "Pista 2 — el cos",
  "description": "Opcional",
  "startsOn": "2026-08-15 00:00:00",
  "endsOn": "2026-08-22 00:00:00",
  "locale": "ca",
  "file": {
    "fileName": "pista-2.pdf",
    "filePath": "{kal_id}/{clue_id}/pista.pdf",
    "fileSize": 184320,
    "fileExtension": "pdf",
    "locale": "ca",
    "uploadId": "<ulid>",
    "uploadedAt": "2026-08-10 12:00:00"
  },
  "meeting": {
    "scheduledAt": "2026-08-16 18:00:00",
    "url": "https://meet.example.com/pista-2",
    "title": "Trobada de la pista 2",
    "timezone": "Europe/Madrid"
  }
}
```

Resposta: `201` + `{"data": {"id": "<ulid de la pista>"}}`. Es torna l'id
perquè el frontend el necessita immediatament per al PATCH i el DELETE, i sense
ell hauria de refer un `GET /kal/{id}` sencer per saber què acaba de crear.

### `PATCH /kal/{kalId}/clue/{clueId}`

Només claus escalars, totes opcionals: `name`, `description`, `startsOn`,
`endsOn`, `locale`. `description` accepta `null` per buidar-la; la resta, si hi
són, han de portar valor.

```json
{ "name": "Pista 2 — el cos", "endsOn": "2026-08-25 00:00:00" }
```

Enviar `id`, `file` o `meeting` és `400 invalid_payload`, igual que el
`PATCH /kal/{id}` rebutja `inviteToken` i `organizerId`. No s'ignoren en silenci:
un client que els envia creu que fan alguna cosa.

### `DELETE /kal/{kalId}/clue/{clueId}`

Sense cos. `204` sense contingut.

### Errors

Cos `{"error": "<missatge>", "code": "<codi>"}`, amb l'status decidit per tipus
d'excepció (`instanceof`), no pel text.

| Cas | Status | `code` |
|---|---|---|
| KAL inexistent, esborrat o d'una altra organitzadora | 404 | `kal_not_found` |
| Pista inexistent, esborrada, o d'un altre KAL | 404 | `clue_not_found` |
| ULID mal format a la ruta, o payload invàlid | 400 | `invalid_payload` |
| Cos que no és JSON | 400 | `invalid_json` |
| `endsOn` <= `startsOn` de la pista | 400 | `kal_invalid_date_range` |
| Dates de la pista fora del rang del KAL | 400 | `kal_clue_outside_range` |
| `locale` de la pista no habilitat al KAL | 400 | `kal_clue_locale_not_enabled` |
| Sense JWT | 401 | — |
| Escriptura fallida a Postgres | 500 | `kal_persistence_failed` |

`clue_not_found` és un codi **nou**: avui no existeix cap excepció de domini per
a una pista que no hi és. Cal `ClueNotFoundException`, mapada a 404 al subscriber
com la resta.

## Scenarios

**Happy path**

1. L'organitzadora afegeix una pista dins del rang del KAL, amb PDF i reunió →
   `201` + l'id. `GET /kal/{id}` la torna niada, amb la seva reunió.
2. Corregeix el nom d'una pista → `204`, i només canvia `name` i
   `clues.updated_at`. El PDF, la reunió i la resta de camps queden igual.
3. Retira una pista → `204`. Deixa de sortir al `GET /kal/{id}`, la seva reunió
   tampoc, i cap de les altres pistes del KAL es mou.
4. Retira l'última pista d'un KAL → `204`. El KAL queda sense pistes, que és un
   estat vàlid.

**Errors i vores**

5. Pista amb dates fora del rang del KAL → `400 kal_clue_outside_range`, res
   escrit.
6. Pista amb un `locale` no habilitat al KAL → `400 kal_clue_locale_not_enabled`.
   Ho fa complir el domini: la BD només valida el format ISO
   (`clues_locale_iso`).
7. PDF amb un `locale` no habilitat → `400 kal_file_locale_not_enabled`.
8. **PATCH que deixa la reunió fora del nou rang** → `400
   kal_meeting_outside_clue_range` i **no es desa res**. Veure Risks: mentre no
   hi hagi endpoint de reunió, això deixa l'organitzadora sense sortida per
   escurçar una pista per sota de la seva trobada.
9. PATCH amb `file` o `meeting` al cos → `400 invalid_payload`. No s'ignoren.
10. PATCH amb cos buit `{}` → `204` i cap escriptura.
11. `clueId` que existeix però pertany a un altre KAL → `404 clue_not_found`.
    El mateix que si no existís.
12. Qualsevol dels tres endpoints sobre un KAL d'una altra organitzadora, o
    esborrat → `404 kal_not_found`, abans de mirar la pista.
13. DELETE d'una pista ja esborrada → `404 clue_not_found`. No és idempotent,
    igual que el `DELETE /kal/{id}`.
14. Pista ja alliberada (`startsOn` passat): PATCH i DELETE funcionen igual,
    `204` totes dues. No hi ha comprovació d'alliberament enlloc.
15. Falla l'INSERT de la reunió després del de la pista → transacció desfeta,
    `500 kal_persistence_failed`, i cap fila de `clues` orfe.

## Acceptance criteria

- [ ] Els tres endpoints existeixen amb els codis d'estat de la taula de Behavior.
- [ ] Cap escriptura toca `kals`; `kals.updated_at` no es mou.
- [ ] El DELETE marca `deleted_at` de la pista **i** de la seva reunió a la
      mateixa transacció, i no esborra cap fila.
- [ ] `GET /kal/{id}` deixa de retornar la pista esborrada i la seva reunió, i
      segueix retornant les altres.
- [ ] Els invariants de rang i de locale es validen **al domini**, no al
      controller ni a la BD, i cap violació deixa res escrit.
- [ ] Un `clueId` d'un altre KAL dona `404 clue_not_found`.
- [ ] Tests: unitaris de domini (`Kal::updateClue` / `removeClue` i les seves
      guardes), unitaris dels tres handlers amb `InMemoryKalRepository`,
      funcionals HTTP dels tres endpoints inclosos 400/401/404, i d'integració
      contra Postgres per a l'ordre dels INSERT, el rollback i el soft delete
      en cascada de la reunió.
- [ ] `make qa` i `make test-db` verds.

## Constraints

- **Capes.** `Domain/` i `Application/` sense Symfony ni DBAL. Els controllers
  només tradueixen HTTP ↔ Commands/Queries pel bus; no toquen SQL ni instancien
  handlers. Ho fan complir els arch tests de `tests/Arch/`.
- **`#[AsController]`** a cada controller nou, i les rutes ja les carrega
  `config/routes/kal.yaml` pel directori — **no cal tocar cap YAML**.
- **PHPStan level max amb `missingCheckedExceptionInThrows`**: cada excepció
  pròpia documentada amb `@throws` per tota la cadena. `ClueNotFoundException`
  n'és una de nova i haurà de propagar-se fins als controllers.
- **Sense migració.** `clues` i `meetings` ja tenen totes les columnes
  necessàries, incloent-hi `deleted_at`
  (`20260811064206_kal_children_soft_delete.sql`). Si la implementació en
  necessita una, és senyal que alguna cosa d'aquesta spec no encaixa: parar i
  revisar-ho.
- **RLS.** Aquests endpoints van amb la service_role key i **salten RLS**, així
  que el filtre `deleted_at IS NULL` ha de ser al repositori, no confiar en les
  policies. Les policies existents ja cobreixen el client directe.
- **Cost.** Cada escriptura carrega l'agregat sencer (sis consultes) per validar.
  Acceptat: són operacions d'organitzadora, poc freqüents, i és el preu de no
  poder escriure una pista que trenqui el KAL.

## Out of scope

- **Substituir el PDF d'una pista.** Demana pensar què passa amb el fitxer antic
  a Storage (esborrar-lo? deixar-lo orfe?) i això és una decisió pròpia.
- **Editar la reunió d'una pista** i afegir-ne de noves per idioma (Fase 2).
- **`GET` d'una pista solta.** Ja surten al `GET /kal/{id}`.
- **Reordenar pistes** amb una columna `position`.
- **Restaurar** una pista esborrada. Lligat a la qüestió oberta del restore de
  `CLAUDE.md`, que segueix sense resoldre's.
- Vídeos per pista, debat per pista, alliberament programat amb recordatoris:
  tot Fase 2.

## Trade-offs

- **Carregar l'agregat a cada escriptura** en comptes d'un `ClueRepository` que
  escrigui directament. Es paga un SELECT del KAL sencer per operació i es
  guanya que cap pista pugui néixer amb dates fora del rang o un locale no
  habilitat, perquè l'arrel sempre hi és pel mig.
- **`PATCH` només escalars.** Una crida per canviar el nom i una altra (futura)
  per moure la trobada, en comptes d'un PUT que ho reemplaci tot. Manté el
  contracte petit i igual que el `PATCH /kal/{id}`; el preu és l'encallada de
  l'escenari 8.
- **Tornar l'id al `POST`** trencant la simetria amb el `POST /kal`, que torna
  `201 {}`. Es prioritza que el frontend no hagi de fer una segona crida just
  quan més necessita l'id.
- **404 en comptes de 403** per a qui no és l'organitzadora. Menys informatiu
  per a un client legítim amb un bug, però no confirma que el KAL existeixi.

## Risks & assumptions

- **Risc principal, escenari 8.** Escurçar una pista per sota de la seva reunió
  dona 400, i com que el PATCH no toca reunions, no hi ha manera d'arreglar-ho
  des de l'API fins que existeixi l'endpoint de reunió. Assumit conscientment:
  es tria mantenir l'invariant abans que obrir el PATCH. Si a les proves amb
  l'organitzadora fundadora surt sovint, la sortida ja identificada és admetre
  `meeting` al PATCH.
- **Assumpció:** el PDF de la pista ja és a Storage quan es crida el `POST`. El
  backend només rep metadades (`filePath`, `uploadId`…) i no comprova que
  l'objecte hi sigui de veritat. Igual que al `POST /kal` d'avui.
- **Assumpció:** una pista no té participants ni progrés associat encara
  (`ClueProgress` no està implementat), així que retirar-ne una no deixa res
  penjant. Quan hi hagi progrés, aquesta decisió s'haurà de revisar.
- **Risc menor:** `clues` no té restricció d'unicitat de dates ni de nom, o sigui
  que es poden crear dues pistes idèntiques. No es considera problema a l'MVP.

## Open questions

1. **Ordre de les pistes al `GET /kal/{id}`.** Avui surten en l'ordre que les
   torna Postgres, sense `ORDER BY`. Amb pistes que s'afegeixen més tard, això
   deixa de ser estable. Val la pena ordenar per `starts_on` a la lectura?
   (No bloqueja aquesta spec, però hi apareixerà de seguida.)
2. **Límit de pistes per KAL.** Cap avui. Fa falta?
3. Quan arribi l'endpoint de reunió, ¿serà
   `PATCH /kal/{kalId}/clue/{clueId}/meeting`, o s'obrirà el PATCH de la pista?
