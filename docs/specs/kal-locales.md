# Spec: editar els idiomes d'un KAL

> Status: **Draft**
> Escrit 2026-08-29. Neix de [`aula-chat.md`](aula-chat.md), que va decidir
> **una aula de xat per idioma habilitat** i va deixar explícitament fora
> l'edició d'idiomes: la invariant que se'n deriva es documentava allà però no
> s'implementava enlloc. Hereta el contracte d'autorització del `CLAUDE.md`
> § «Dues superfícies d'API» (organitzadora o 404) i el criteri d'esborrat de
> § «Soft delete: com s'esborra».

---

## Problem

**Els idiomes habilitats d'un KAL es fixen al crear-lo i no es mouen mai.** No és
un descuit que s'hagi d'anar a buscar: hi ha una porta tancada a cada capa.

- `Locales` (`src/Kal/Domain/Locales.php`) és `final readonly` i exposa només
  `create()`, `contains()` i `all()`. No té `add` ni `remove`.
- `Kal::update()` rep **cinc escalars** — `name`, `description`, `startsOn`,
  `endsOn`, `coverPath` — i prou. Els idiomes no hi passen.
- `UpdateKalCommand::$changes` enumera aquestes mateixes cinc claus al docblock,
  i `KalUpdateController::REJECTED_FIELDS` inclou `'locales'`: enviar-los al
  `PATCH /kal/{id}` avui és `400 invalid_payload`, no un no-op.
- `KalRepository::update()` és un sol `UPDATE kals SET …` de sis columnes. El
  docblock de `KalRepositoryInterface::update()` ho diu amb totes les lletres:
  «Does not touch locales, files, clues, meetings or debate rooms».
- El `KalHydrator` **sí** que sap extreure les files de `kal_locales`
  (`extract()['locales']`), però l'únic que les consumeix és
  `KalRepository::create()`.

Els idiomes no són decoratius: són l'eix pel qual es reparteix el contingut del
KAL. Tres invariants de l'arrel hi pengen avui —
`guardFilesLocaleEnabled()`, `guardCluesFileLocaleEnabled()` i
`guardCluesLocaleEnabled()`, cridades tant a `create()` com a `reconstitute()` —
i `aula-chat.md` n'hi afegeix una quarta, l'aula per idioma. Un idioma no és una
etiqueta: és un contenidor amb pistes, fitxers i conversa a dins.

L'efecte pràctic és el mateix que descrivia `clue-crud.md` abans d'existir:
l'organitzadora que obre el KAL en `ca` i al cap de dues setmanes hi vol `es`
—perquè s'hi han apuntat participants castellanoparlants, que és exactament com
passa— només té la sortida d'esborrar el KAL i tornar-lo a crear, cosa que li
canvia l'`inviteToken` i deixa fora tothom qui ja s'hi havia apuntat.

## Goals / Non-Goals

**Goals**

- L'organitzadora pot **afegir i treure** idiomes d'un KAL ja creat.
- La invariant «un idioma habilitat ⇔ una aula viva» es manté a cada
  escriptura, sense finestres intermèdies: afegir un idioma crea la seva aula a
  la mateixa transacció.
- Treure un idioma amb conversa **es bloqueja amb un error de domini**, mai amb
  un esborrat silenciós.
- Els invariants de locale de pistes i fitxers segueixen sent certs després del
  canvi: cap escriptura pot deixar l'agregat en un estat que `reconstitute()` no
  sàpiga tornar a llegir.
- El contracte d'errors i d'autorització és el de la resta del CRUD
  d'organitzadora: qui no ho és rep `404 kal_not_found`, mai 403.

**Non-Goals**

- **Traduir res.** Habilitar `es` no tradueix cap pista ni cap PDF: només obre
  el contenidor perquè l'organitzadora hi pengi contingut en aquell idioma.
- **Que la participant triï «el seu» idioma.** Ja està decidit a `aula-chat.md`:
  una membre veu totes les aules del seu KAL i tria on escriu. Els idiomes que
  una persona parla no són dada del sistema.
- Múltiples aules per idioma, o múltiples reunions per idioma dins d'un mateix
  àmbit: Fase 2, i no els toca aquesta spec.
- Canviar el `check` ISO de `kal_locales` ni afegir cap llista tancada
  d'idiomes permesos. La validació de format ja la fa
  `kal_locales_locale_iso` i el VO `Locale`.
- Restaurar. El `deleted_at` d'una filla no diu qui l'ha marcat (CLAUDE.md
  § «Qüestió oberta, conscientment»), i aquesta spec no ho resol.

## La invariant

> **Cada idioma habilitat d'un KAL té exactament una aula viva, i cap aula viva
> pertany a un idioma que el KAL no tingui habilitat.**

D'aquí surten les dues regles d'edició, una per cada direcció:

- **Afegir un idioma crea la seva aula**, automàticament i a la mateixa
  transacció. No és una acció a part de l'organitzadora: una aula que no
  existeix no té on rebre missatges, i `KalHydrator` ja llença
  `KalStateException::missingDebateRoom()` quan el recompte d'aules no quadra.
  Un idioma habilitat sense aula seria un KAL que dona 500 al `GET`.
- **Treure un idioma amb missatges a l'aula es bloqueja** — `KalException`
  pròpia, no un esborrat silenciós. Esborrar conversa d'altri com a efecte
  secundari d'un canvi de configuració no és un comportament que ningú perdoni.
  Si l'aula no té missatges, marxa amb l'idioma: soft delete, mateixa
  transacció, mateix criteri que la cascada del KAL.

### El fet ve d'un port, no de l'agregat

`debate_messages` **no és de l'agregat Kal**: l'escriu el frontend directament
contra Supabase i el backend no la llegeix mai (només l'escriu, i només per la
cascada del soft delete — veure `aula-chat.md`). L'agregat no pot saber sol si
una aula té conversa.

Tampoc ho pot resoldre un event: la comprovació ha de passar **abans** del
canvi, i els events es registren després. Per això el handler pregunta al port i
li passa el fet ja resolt al domini:

```php
// Application: el handler consulta el port abans de tocar l'agregat.
$kal->updateLocales($desired, $this->debateActivity->localesWithMessages($kalId));
// Domain: llença KalException::localeHasActiveDebate() si en toca cap.
```

Amb `DebateActivityPort` declarat a `Kal/Domain/` com la resta de ports. Així la
regla segueix sent **de domini i testejable sense Postgres**, en comptes
d'acabar en un trigger d'SQL on ningú la busca.

### El límit de la FK

A `aula-chat.md`, `debate_rooms` guanya una FK composta `(kal_id, locale)` cap a
`kal_locales`. Cobreix una sola direcció: «no pots crear una aula en un idioma
que el KAL no té habilitat». **No cobreix treure un idioma.** `kal_locales` té
`deleted_at` des de la migració `20260811064206_kal_children_soft_delete.sql`, i
en aquest projecte l'app no fa mai un `DELETE` d'SQL: treure un idioma serà un
`UPDATE … SET deleted_at`, la fila es queda i la FK no es dispara mai. **Aquesta
meitat de la regla ha de viure al domini**, i no hi ha manera d'externalitzar-la
a l'esquema sense canviar el criteri de soft delete de tot el projecte.

## Esquema

**Aquesta spec no necessita cap taula ni cap columna nova.** `kal_locales` ja té
`(kal_id, locale)`, `deleted_at` i el `check` ISO; l'única cosa que aquest camí
escriu són files d'aquesta taula i de `debate_rooms`. Dos detalls de l'esquema
d'avui que condicionen el disseny, tots dos verificats:

- **`kal_locales_pk` és una PK total, no un índex parcial.** És
  `primary key (kal_id, locale)`, sense `where deleted_at is null`, i cap
  migració posterior l'ha tocat. Conseqüència directa: **tornar a afegir un
  idioma que s'havia tret no pot ser un `INSERT`** — la fila soft-deleted encara
  hi és i el violaria. O és un `UPDATE … SET deleted_at = null` (ressuscitar la
  fila), o cal fer la PK parcial amb una migració. Contrasta amb l'índex nou que
  proposa `aula-chat.md` per a `debate_rooms`, que sí que és parcial: les dues
  taules no es comporten igual, i qui implementi això ho ha de saber abans
  d'escriure la primera línia.
- **`kal_locales` és només de lectura per al client: el backend n'és l'únic
  escriptor.** Des del 2026-08-29
  (`20260829210457_lock_kal_writes_to_backend.sql`), `authenticated` hi té només
  `grant select` i l'única política que hi queda és `kal_locales_select_member`;
  el `grant insert` de `20260804211249_rls_grants_authenticated.sql` i la
  política `kal_locales_insert_organizer` ja no existeixen. Per al camí
  d'aquesta spec és indiferent —el backend va amb service_role i salta tant la
  RLS com els grants—, però és el que fa que la regla d'aquí sigui **l'única**
  regla: no hi ha cap segona porta per la qual un idioma pugui aparèixer o
  desaparèixer. Veure Risks.

## Conseqüències sobre codi que ja existeix

Editar idiomes toca l'agregat sencer, no un mètode:

- **`Locales` ha de deixar de ser `final readonly`.** `Kal::$locales` és
  `public private(set) readonly Locales`, o sigui que la propietat no es pot
  reassignar ni des de dins de la classe. El patró que l'agregat ja fa servir
  per a una col·lecció mutable és `Clues`: classe **no** readonly, amb `add()`,
  `replace()` i `remove()`, darrere d'una propietat readonly que mai canvia
  d'objecte. `Locales` ha de seguir el mateix camí; canviar la propietat de
  `Kal` seria el camí equivocat.
- **`Kal` guanya el mètode d'edició d'idiomes** i, amb ell, els guards en la
  direcció contrària a la d'avui. Els tres guards existents comproven «el locale
  d'aquesta pista/fitxer és dels habilitats» recorrent les filles; treure un
  idioma és el mateix fet mirat des de l'altre costat, i pot reutilitzar-los
  —però l'error que en surt (`kal_clue_locale_not_enabled`) explica malament què
  ha passat quan qui ha canviat és la llista d'idiomes, no la pista.
- **`UpdateKalCommand` i `KalUpdateController`**, si el camí és el `PATCH` que
  ja existeix: la forma de `$changes` creix, i `locales` ha de sortir de
  `REJECTED_FIELDS` (avui hi és, i rebutja la petició amb 400). Veure Open
  questions: aquesta decisió no està presa.
- **`KalRepository::update()` deixa de ser un sol `UPDATE`** i passa a obrir
  transacció, com ja fan `create()`, `delete()`, `addClue()` i `deleteClue()`.
  El docblock de `KalRepositoryInterface::update()` («Does not touch locales,
  files, clues, meetings or debate rooms») deixa de ser cert i s'ha de reescriure
  —o el camí d'idiomes viu en un mètode propi del port, com va fer `clue-crud.md`
  amb `addClue`/`updateClue`/`deleteClue` en comptes d'inflar `update()`.
- **`KalHydrator`**: `extract()['locales']` ja produeix les files
  `{kal_id, locale}`, però el camí d'escriptura necessita saber **quines**
  afegir i quines marcar, no la llista sencera. I `extract()['debate_room']`
  (singular) passa a ser la col·lecció que descriu `aula-chat.md`.
- **`KalRepository::CHILD_TABLES` no es toca.** Aquesta és la cascada del soft
  delete del KAL; treure un idioma és un esborrat propi de l'entitat, com el de
  les pistes.
- **`InMemoryKalRepository`** (`tests/Unit/Kal/Infrastructure/Persistence/`)
  implementa `KalRepositoryInterface` i es fa servir a tots els tests de
  feature: qualsevol mètode nou al port hi aterra també, i el `DebateActivityPort`
  necessita el seu propi doble.
- **`ApiExceptionMapper` no s'ha de tocar** per a l'error nou: `KalException`
  estén `DomainException` sense ser `NotFoundException`, `ConflictException` ni
  `CorruptedStateException`, i el mapper li dona `400` amb el seu `errorCode()`
  pel camí per defecte. Si es volgués `409`, això sí que seria un canvi de tipus
  d'excepció (veure Open questions).
- **`GetKalResponse` no canvia**: ja emet `locales` com a llista de codis.
- **`CLAUDE.md`**: la descripció de l'agregat Kal guanya la invariant d'aquesta
  spec. La línia «MVP: 1 sola aula» ja la deroga `aula-chat.md`.

## Els events

L'agregat **registra** `KalLocaleAdded` i `KalLocaleRemoved` amb `recordEvent()`,
però **els events no són el mecanisme**. El canvi d'estat —crear o esborrar
l'aula— el fan l'agregat i el repositori a la mateixa transacció, exactament com
`KalRepository::create()` ja fa amb l'aula inicial i com el
`CreateKalCommandHandler` fa amb la fitxa del patró: «sense event ni listener»,
que diu CLAUDE.md. Amb transport sync el listener corre al mateix request però
obre la seva pròpia transacció, i hi hauria una finestra amb l'idioma habilitat
i sense aula — precisament l'estat que `missingDebateRoom()` declara trencat.

Els events hi són **per al que ve després i cap enfora**: avisar les membres que
s'ha obert una aula en un idioma nou, invalidar cache al frontend, i el dia que
el debat surti a context propi.

**Avís abans de comptar-hi, verificat:** avui **no hi ha cap event de domini
publicat a tot el projecte**. `Kal` estén `AggregateRoot`, però `recordEvent()` i
`pullDomainEvents()` no tenen cap crida enlloc fora de la pròpia classe
`AggregateRoot` — cap repositori ni cap handler fa el `pull` per despatxar-los al
bus. La infraestructura hi és (els quatre busos, el message store); el camí de
publicació, no. Construir-lo és una tasca pròpia: fins que existeixi, aquests dos
events es registren i no els escolta ningú.

## Scenarios

_(pendent)_

## Acceptance criteria

_(pendent)_

## Constraints

_(pendent)_

## Out of scope

_(pendent)_

## Trade-offs

_(pendent)_

## Risks & assumptions

Els tres contrastats amb el codi i les migracions d'avui. El primer ja no és un
risc obert; es conserva perquè explica per què la regla d'aquesta spec ara basta:

- **El client podia inserir a `kal_locales` sense passar pel backend; es va
  tancar el 2026-08-29.** Era un forat real, verificat abans de tancar-lo:
  `grant insert on … kal_locales … to authenticated`
  (`20260804211249_rls_grants_authenticated.sql`) + la política
  `kal_locales_insert_organizer` deixaven que una organitzadora amb `supabase-js`
  habilités un idioma saltant-se tota la regla d'aquesta spec — i, amb
  `aula-chat.md` aplicat, l'idioma hauria quedat sense aula i el recompte del
  `KalHydrator` hauria fet petar el `GET /kal/{id}` amb un 500. El grant i la
  política van caure amb `20260829210457_lock_kal_writes_to_backend.sql`, per la
  decisió transversal que **tota escriptura de gestió passa pel backend**; no va
  ser una decisió d'aquesta spec ni de la del xat. El que en queda per a qui
  implementi això no és un risc sinó una simplificació: **el camí del backend és
  l'únic camí**, i la invariant només s'ha de fer complir al domini.
- **La regla d'esborrat no la pot fer complir la BD.** Ja explicat a «El límit de
  la FK»: amb soft delete, la FK composta de `debate_rooms` no es dispara mai en
  treure un idioma. Si el domini se salta la comprovació, no hi ha segona xarxa.
- **Cap event de domini es publica avui.** Verificat a «Els events»: registrar
  `KalLocaleAdded`/`KalLocaleRemoved` no fa que arribin enlloc.

Supòsit que hereta d'`aula-chat.md` i que envelleix igual de malament: **no hi ha
dades reals a cap entorn**, ni desplegament ni remot. Si això deixa de ser cert
abans d'implementar-ho, el cas «treure un idioma que ja té conversa» passa de ser
teòric a ser el primer que trobarà algú.

## Open questions

1. **Què passa en treure un idioma que té pistes o fitxers?** És el segon cas
   del mateix pes que el de la conversa, i no està decidit. `clues.locale` i
   `kal_files.locale` són per idioma, i el domini ja fa complir que siguin dels
   habilitats (`guardCluesLocaleEnabled()`, `guardFilesLocaleEnabled()`): si es
   treu l'idioma sense fer res més, l'agregat deixa de poder-se `reconstitute()`
   i el KAL queda il·legible. Les sortides no són equivalents: bloquejar (mateix
   criteri que la conversa, i el més conservador), arrossegar les pistes i
   fitxers al soft delete (destrueix feina de l'organitzadora, però és feina
   *seva*, no d'altri), o obligar a moure'ls d'idioma abans. Decisió de
   l'arquitecta.
2. **`PATCH /kal/{id}` o endpoint propi?** El `PATCH` ja existeix i avui rebutja
   `locales` explícitament; obrir-lo és treure una línia d'una constant. A
   favor d'un endpoint propi: aquest camí té errors, transacció i efectes
   secundaris (crear i esborrar aules) que no s'assemblen gens als cinc escalars
   que el `PATCH` mou avui, i `clue-crud.md` ja va prendre la decisió anàloga de
   treure les pistes del `PATCH` del KAL.
3. **Es pot tornar a afegir un idioma que s'havia tret?** Tècnicament,
   l'aula vella té `deleted_at` i l'índex únic nou de `debate_rooms` és parcial
   (`where deleted_at is null`), o sigui que se'n pot crear una de nova — però
   llavors la conversa antiga queda **òrfena i invisible**, i ningú té manera de
   recuperar-la. I hi ha el detall de l'esquema: `kal_locales_pk` **no** és
   parcial, així que la fila de l'idioma no es pot reinserir; o es ressuscita
   (`deleted_at = null`, que retornaria també l'aula vella i la seva conversa) o
   cal migració. Les dues sortides tenen semàntica ben diferent i cap és òbvia.
4. **Reemplaçament o delta?** El payload és la llista completa d'idiomes
   desitjats (`{"locales": ["ca","es"]}`, i el que falta es treu) o són
   operacions explícites d'afegir i treure? La primera forma és la que suggereix
   la signatura `updateLocales($desired, …)` d'aquesta spec i encaixa amb el
   `PATCH`; la segona fa impossible treure un idioma per accident amb un client
   que envia una llista incompleta.
5. **Es pot treure l'últim idioma?** `Locales::create()` ja llença
   `kal_no_locales_enabled` amb la llista buida, o sigui que la invariant ≥1 ja
   existeix i el comportament sortiria sol — cal confirmar que l'error que veu
   l'organitzadora («almenys un idioma») és el correcte i no un 500 disfressat.
6. **400 o 409 per «l'idioma té conversa»?** Amb `KalException` tal com és avui
   surt `400` automàticament. Però no és un payload mal format: és un conflicte
   amb l'estat actual del recurs, i `ConflictException` ja existeix i mapa a
   `409`. Afecta com el frontend ho ha de presentar (corregeix la petició vs.
   explica per què no es pot).
7. **Quins missatges compten com a «conversa»?** Els soft-deleted i els `hidden`
   segueixen sent files a `debate_messages`. Si el port només mira
   `deleted_at is null`, una aula on tothom va esborrar el que havia escrit es
   considera buida i marxa amb l'idioma, enduent-se files que encara hi són.
