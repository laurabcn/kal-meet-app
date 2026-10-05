# Spec: posar o substituir el PDF del patró d'un KAL

> Status: **Implementada** a la branca `feat/put-kal-file` (2026-10-05).
> Escrit 2026-10-03. Neix de la revisió de la PR #21 (un sol idioma i com a
> molt un PDF per KAL). Hereta el contracte d'autorització del `CLAUDE.md`
> § «Dues superfícies d'API» (organitzadora o 404) i el criteri d'esborrat de
> § «Soft delete: com s'esborra».

---

## Problem

**El PDF del patró d'un KAL només es pot donar en crear-lo.** Des de la PR #21
un KAL té com a molt un fitxer (`Kal::$file`, `?File`), i l'única porta
d'entrada és el `file` del `POST /kal`. Després, cap camí:

- `Kal::$file` és `public private(set) readonly`: ni el domini el pot canviar.
- `PATCH /kal/{id}` rebutja `file` (i `files`) amb `400 invalid_payload`, a
  propòsit: el `PATCH` és d'escalars.
- `KalRepository::update()` només escriu la fila de `kals`.

El cas habitual el deixa fora: l'organitzadora anuncia el KAL i obre
inscripcions **abans** de tenir el patró tancat, i el PDF arriba després. També
el d'una errata: tenir-ne una versió corregida i no poder-la pujar. Avui
l'única sortida seria esborrar el KAL i tornar-lo a crear, perdent
participants i enllaç d'invitació.

## Goals / Non-Goals

**Goals**

- L'organitzadora pot posar el PDF del patró a un KAL que no en té, i
  substituir el que ja té, amb un sol endpoint: `PUT /kal/{kalId}/file`.
- Substituir és **atòmic**: el fitxer anterior queda marcat amb `deleted_at` i
  el nou s'escriu a la mateixa transacció. Mai hi ha dos fitxers vius, i un KAL
  que en tenia un no es queda mai sense, ni un instant.
- **Idempotent**: un `PUT` amb el mateix `uploadId` que el fitxer viu és un
  no-op que torna `204`. Un reintent de xarxa no omple `kal_files` de còpies
  esborrades del mateix fitxer.
- Autorització i errors iguals que la resta del CRUD d'organitzadora: el
  `kalId` i l'organitzadora del JWT van al `WHERE`, i qui no n'és rep
  `404 kal_not_found`.

**Non-Goals**

- Pujar el binari: el client el puja directament a Storage (bucket
  `kal-patterns`) i aquest endpoint només registra les metadades i el path,
  com ja fa el `POST /kal`.
- Treure el PDF sense posar-ne un altre.
- Tocar el PDF de les pistes.

## Inputs / Outputs

### `PUT /kal/{kalId}/file`

El cos **és** el fitxer, sense embolcall: el recurs de la ruta ja és `file`. La
forma és exactament la del `file` del `POST /kal` (`CluePayloadFactory::file`),
perquè el client no n'hagi d'aprendre dues.

```json
{
  "fileName": "patro.pdf",
  "filePath": "{kal_id}/ca.pdf",
  "fileSize": 184320,
  "fileExtension": "pdf",
  "uploadId": "<ulid>",
  "uploadedAt": "2026-10-03 12:00:00"
}
```

- Sense `locale`: el fitxer parla l'idioma del KAL, i enviar-lo és `400`.
- `fileExtension`: el que ja accepta `FileExtension` (`pdf`, `csv`, `txt`).
- Resposta: `204`, cos buit, tant si posa, si substitueix com si és el no-op.
- Si el fitxer canvia, el KAL mou `updatedAt` (el fitxer és part de
  l'agregat). El no-op no el mou, igual que un `PATCH` buit.

### Errors

| Cas | Status | `code` |
|---|---|---|
| KAL inexistent, esborrat o d'una altra organitzadora | 404 | `kal_not_found` |
| `kalId` no és un ULID, o el fitxer és invàlid (camp que falta o de tipus incorrecte, porta `locale`) | 400 | `invalid_payload` |
| `fileSize` ≤ 0 | 400 | `file_size_not_positive` |
| `fileSize` per sobre del màxim de `FileSize` | 400 | `file_size_exceeded` |
| `fileExtension` no permesa | 400 | `kal_file_invalid_type` |
| `uploadId` ja usat per una altra fila de `kal_files` (un fitxer esborrat d'aquest KAL o de qualsevol altre) | 400 | `invalid_payload` |
| Cos que no és JSON | 400 | `invalid_json` |
| Sense JWT | 401 | — |
| Escriptura fallida a Postgres | 500 | `kal_persistence_failed` |

## Scenarios

**Happy path**

1. **KAL sense PDF**: l'organitzadora fa `PUT` amb un fitxer vàlid → `204`.
   `GET /kal/{id}` torna aquest `file`, el KAL té `updatedAt` nou, i a
   `kal_files` hi ha una fila viva.
2. **KAL amb PDF**: `PUT` amb un fitxer nou (un altre `uploadId`) → `204`.
   `GET /kal/{id}` torna el nou. A `kal_files` hi ha una sola fila viva (la
   nova) i l'anterior hi segueix amb `deleted_at` informat, marcada a la
   mateixa transacció.
3. **Reintent**: el mateix `PUT` de l'escenari 2, repetit → `204`, no s'escriu
   res, `updatedAt` no es mou i a `kal_files` no apareix cap fila nova.

**Errors i vores**

4. **KAL d'una altra organitzadora, inexistent o esborrat** → `404
   kal_not_found`, i no es toca `kal_files`. Igual per a una participant.
5. **Fitxer invàlid** (falta `fileName`, `fileSize` 0, extensió `docx`, porta
   `locale`) → `400` amb el codi de la taula, i el fitxer viu, si n'hi ha,
   segueix sent el mateix.
6. **`uploadId` d'un fitxer ja esborrat** (per exemple, tornar a enviar el de
   l'escenari 1 després de l'escenari 2) → `400 invalid_payload`. Mai un `500`:
   `upload_id` és la clau primària de `kal_files`, i un `INSERT` sense mirar-ho
   petaria. Mateix resultat si l'`uploadId` és d'un fitxer d'un altre KAL.
7. **Fallada a mitja substitució** (Postgres cau entre el `SET deleted_at` i
   l'`INSERT`) → `500 kal_persistence_failed`, i el fitxer anterior **segueix
   viu**: la transacció es desfà sencera.

## Acceptance criteria

**Comportament**

- [x] `PUT /kal/{kalId}/file` existeix, autenticat, i compleix els escenaris 1–7.
- [x] Després de qualsevol `PUT` reeixit hi ha **exactament una** fila viva a
      `kal_files` per al KAL, i cap fila s'esborra de veritat (`deleted_at`,
      mai `DELETE`).
- [x] El no-op (mateix `uploadId` que el fitxer viu) no escriu cap fila, ni a
      `kal_files` ni a `kals`.
- [x] Cap cas de la taula d'errors acaba en `500`, fora de la fallada real de
      Postgres.

**Capes** (és una spec curta sense secció de Constraints; van aquí)

- [x] El canvi de fitxer és un mètode de domini a `Kal`, que també mou
      `updatedAt`. `Kal::$file` deixa de ser `readonly`, però continua sent
      `private(set)`. Ni el controller ni el handler assignen el fitxer.
- [x] Un command i el seu handler, despatxats pel `command.bus`. El controller
      porta `#[AsController]`, no toca SQL ni instancia el handler.
- [x] El repositori afegeix un mètode que escriu **només** `kal_files` (marcar
      l'anterior, inserir el nou) i `kals.updated_at`, en una transacció.
      L'`UPDATE` de `kals` porta `id`, `organizer_id` i `deleted_at IS NULL` al
      `WHERE`, com a `update()`; `kal_files` no té `organizer_id`, o sigui que
      és aquell `UPDATE` qui garanteix que el KAL és de l'organitzadora dins de
      la transacció.
- [x] `@throws` a tota la cadena, sense entrades noves a la baseline de
      PHPStan.

**Tests**

- [x] Domini: posar, substituir i no-op (amb `updatedAt` que es mou o no).
- [x] Handler, contra `InMemoryKalRepository`: els tres camins i el 404.
- [x] Feature (HTTP): els escenaris 1–6, inclosos els codis de la taula
      d'errors.
- [x] Integració (`make test-db`), contra Postgres real: després de substituir
      queda una fila viva i l'anterior marcada; `uploadId` reaprofitat → cap
      `500`; la fallada a mitja transacció deixa viu el fitxer anterior.
- [x] `make qa` i `make test-db` verds.

## Out of scope

- **Esborrar el binari antic de Storage** (bucket `kal-patterns`). En
  substituir, la fila anterior queda marcada però el PDF segueix a Storage. És
  la mateixa decisió que `clue-crud.md` va deixar oberta per al PDF de les
  pistes (esborrar-lo o deixar-lo orfe), i s'ha de prendre una vegada per a
  totes dues.
- **Treure el PDF sense posar-ne un altre** (`DELETE /kal/{kalId}/file`).
- **Substituir el PDF d'una pista.**
- **Validar que `filePath` existeixi de veritat a Storage**, o que sigui del
  bucket i del KAL correctes. Avui el `POST /kal` tampoc ho fa; si cal, és una
  tasca per a tots dos camins.
- **Restaurar un fitxer anterior.** Els que queden marcats no es poden reviure
  per aquest endpoint (escenari 6).

## Decisions preses (abans «Open questions»)

1. **`uploadId` reaprofitat: es captura la violació de `kal_files_pkey`**, sense
   `SELECT` previ, i per tant sense finestra de carrera. El repositori la
   converteix en `KalFileException::uploadIdAlreadyUsed()`, que porta el codi
   `invalid_payload`: és un `DomainException`, i el mapper ja el tradueix a 400
   sense cap canvi. És el mateix patró que `kals_pkey` →
   `KalAlreadyExistsException` a `create()`. Qualsevol altra violació d'unicitat
   continua sent `kal_persistence_failed` (500).
2. **L'escenari 7 no necessita cap fila esquer.** Reaprofitar l'`uploadId`
   d'un fitxer ja marcat fa petar l'`INSERT` *després* d'haver marcat el fitxer
   viu: és exactament la fallada a mitja substitució. El test d'integració pot
   córrer dins la transacció del test (el repositori obre un savepoint), perquè
   comprova que el fitxer viu *hi és*, no que no hi hagi res. Comprovat mutant
   el repositori: sense la transacció, en fallen tres.
3. ~~Dependència de la PR #21.~~ Ja no aplica: la #21 va entrar a `main` amb la
   PR #22.

**Decisions d'implementació que la spec no fixava:**

- **L'ordre dins la transacció: primer l'`UPDATE kals`.** A més de comprovar
  la propietat, bloqueja la fila del KAL. Així dos `PUT` simultanis fan cua en
  comptes de topar amb l'índex únic parcial `kal_files_kal_id_active_unique`,
  que donaria un 500.
- **Dos `PUT` del mateix fitxer alhora també són un no-op (`204`).** Si el
  client reintenta amb la primera petició encara en curs, totes dues carreguen
  el KAL abans que l'altra faci commit, i el no-op del domini no atura cap de
  les dues. La segona espera el bloqueig i topa amb `kal_files_pkey`. Abans de
  respondre `400`, el repositori mira si aquell `uploadId` ja és el fitxer viu
  d'aquest KAL: si ho és, torna sense error.
- **El 404 surt abans que el 400 del fitxer.** El controller només valida
  l'ULID i el JSON; el cos el valida el handler *després* de carregar el KAL.
  Així ningú pot saber quins KALs existeixen enviant fitxers invàlids. És
  l'ordre del CRUD de pistes, i no el del `PATCH /kal`, que valida el cos al
  controller.
- **El no-op el decideix el domini:** `Kal::replaceFile()` torna `false` si
  l'`uploadId` és el del fitxer viu, i el handler aleshores no crida el
  repositori.

**Trobat de passada, ja arreglat:** una extensió no permesa donava
`kal_file_invalid_status` en comptes de `kal_file_invalid_type`
(`FileExtension::tryFromStatus()` llançava l'excepció de `FileUploadStatus`).
Afectava també el `POST /kal`.

**Trobat de passada, pendent:** el `POST /kal` amb un `uploadId` de fitxer ja
existent dona `500 kal_persistence_failed`, perquè `create()` només distingeix
`kals_pkey`. És el mateix forat de l'escenari 6, però en un altre endpoint, i
queda fora d'aquesta spec.
