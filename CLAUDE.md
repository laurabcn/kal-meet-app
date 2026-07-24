# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

The backend of **KAL App**: a web tool for KAL (knit-along) organizers, replacing the current
patchwork of Instagram + Google Forms + Telegram/Discord + spreadsheets with a single app. PHP 8.5,
Symfony 8, Doctrine DBAL (SQL directe, sense ORM), Pest, PHPStan i PHP CS Fixer. Tot corre a Docker —
no hi ha instal·lació local de PHP, així que totes les comandes van via `make` / `docker compose run`.

This repo started life as a reusable take-home-challenge boilerplate; that framing is now retired —
this is the real product, not a template. `src/Shared` is still the domain-agnostic CQRS/messaging
kernel described below, but everything else under `src/` is KAL App domain code, not
challenge-of-the-day scaffolding.

**Usuàries:** organitzadores/dissenyadores de patrons i participants. Ús mixt: desktop/tauleta per
seguir el KAL i les trobades (estones llargues), mòbil per pujar fotos i apuntar-se (moment estrella,
ha de ser impecable). Disseny responsive, cap dels dos de segona.

**Hipòtesi de monetització (a validar, no tancada):** l'organitzadora/dissenyadora paga, les
participants gratis; pot evolucionar a mesura que es defineixi el producte i arribin les entrevistes.
El que NO canvia: el client és l'organitzadora — qui pren la decisió d'usar l'eina i sent el dolor que
resolem. A qui servim primer, encara que algun dia les participants paguin per alguna cosa.

**Estat:** MVP en construcció per una sola desenvolupadora sènior de PHP (10+ anys: Symfony, DDD,
hexagonal, CQRS, Event Sourcing; frontend menys). Projecte paral·lel a mitja jornada — prioritzar la
solució simple sobre l'elegant. La desenvolupadora és l'arquitecta: els agents proposen, ella decideix.
Davant d'un dubte de disseny, preguntar-li, no assumir.

**Visió més enllà de l'MVP:** incorporar IA i RAG tant per assistir l'organitzadora en la creació del
patró (omplir la fitxa des del PDF, suggerir estructura de pistes, descripcions, detectar
llanes/gruixos) com per al descobriment/recomanació de patrons. L'MVP no ho implementa, però les
decisions de disseny (patró com a futur agregat propi, fitxa estructurada, PDFs accessibles) han de
deixar-hi el terreny preparat.

## Commands

```bash
make setup              # build the image, install Composer dependencies
make build               # (re)build the app image only
make bash                 # interactive shell in the app container
make serve                # serve the HTTP skeleton at http://localhost:8000

make test                 # run the full Pest suite
make run-arch             # run only the architecture tests (tests/Arch)
make run-tests-filter p='name'   # run Pest filtered by test name
make run-tests-retry      # re-run only tests that failed last time

make qa                   # PHPStan + CS Fixer (dry-run) + Pest — run this before considering work done
make run-phpstan          # PHPStan only
make run-cs-fixer         # CS Fixer, dry-run/diff only (does not modify files)
make run-cs-fixer-fix     # auto-fix code style violations

make composer-require p=vendor/package       # require a runtime package
make composer-require-dev p=vendor/package   # require a dev package
```

Run `make help` for the full, grouped list. There is no `bin/console`; this project only pulls in `symfony/console` + `symfony/dependency-injection` (plus the optional HTTP skeleton), not a CLI application skeleton — add one if needed.

## Architecture

### Composition root

`src/Kernel.php` uses `MicroKernelTrait` with no overrides — all wiring is convention-based config in `config/`:
- `config/bundles.php` — registered bundles (`FrameworkBundle`, `DoctrineBundle`).
- `config/services.yaml` — `App\` is autowired/autoconfigured by default. The four message buses (see below) are bound to constructor parameters by **name** (`$commandBus`, `$queryBus`, `$eventBus`, `$externalMessageBus`), not by type — a class that wants a specific bus must name its constructor parameter accordingly.
- `config/packages/messenger.yaml` — declares the four named buses and attaches `MessageStoreMiddleware` to `command.bus`, `event.bus` and `external_message.bus` (not `query.bus`, since queries are never `Storable`).
- `config/packages/doctrine.yaml` — DBAL connection only.

### `src/Shared` — the CQRS/messaging kernel

This is the one substantial piece of domain-agnostic infrastructure the codebase ships with; everything else under `src/` is KAL App domain-specific. It follows DDD/hexagonal layering (`Domain` → `Application` → `Infrastructure`) and is organized around **four independent Symfony Messenger buses**, each with its own adapter in `Shared/Infrastructure/Symfony/Bus/`:

| Bus | Interface | Adapter | Purpose |
|---|---|---|---|
| `command.bus` | `CommandBusInterface` | `SymfonyCommandBus` | in-process writes, exactly one handler |
| `query.bus` | `QueryBusInterface` | `SymfonyQueryBus` | in-process reads, exactly one handler, returns a `ResponseInterface` |
| `event.bus` | `DomainEventBusInterface` | `SymfonyDomainEventBus` | in-process domain events, zero-or-more handlers |
| `external_message.bus` | `ExternalMessageBusInterface` | `SymfonyExternalMessageBus` | cross-bounded-context messages, produced and consumed over a transport |

Key conventions to know before touching this code:

- **Storable vs plain messages**: `StorableCommandInterface`/`StorableEventInterface` (both extend `StorableMessageInterface` → `ExternalMessageInterface`) mark messages that `MessageStoreMiddleware` should persist via `MessageStoreRepositoryInterface`. A command/event only needs to implement one of these if it must be recorded in the message store; plain `CommandInterface`/`DomainEventInterface` implementations pass through untouched.
- **Producing vs consuming**: `MessageStoreMiddleware` tells the two apart by the presence of a `ReceivedStamp` (present when the message came off a transport). On the consuming side the store lookup key is the transport name; on the producing side it's `$message::boundedContext()`. `MessageStoreRepositoryMapper::find()` matches a transport name back to a bounded context by the `{bc-kebab-case}-{suffix}` naming convention.
- **`AggregateRoot`** (`Shared/Domain/Model`) accumulates domain events via `recordEvent()`/`pullDomainEvents()` — the standard base for any aggregate that needs to publish events after a write.
- **Value objects** (`Shared/Domain/ValueObject`) are the primitives (`UlidValue`, `DateTime`, `NonEmptyStringValue`, `PositiveIntegerValue`, `BooleanValue`) domain code should use instead of raw scalars.

### Architecture tests (`tests/Arch`)

Pest arch tests are the enforcement mechanism for the layering rules above, and are templates meant to be extended per bounded context (not just left as-is):
- `LayerBoundariesTest.php` — `Domain` must not depend on `Application`, `Infrastructure`, Symfony or Doctrine; `Application` must not depend on `Infrastructure`; nothing outside `Infrastructure` (except the `Kernel`) may depend on it.
- `ConventionsTest.php` — strict types in `Domain`/`Application`, `Domain` classes final, controllers final + single-action invokable, no debugging leftovers (`dd`, `dump`, `var_dump`, `print_r`) anywhere.

Both files currently check against the bare `App\Domain`/`App\Application`/`App\Infrastructure` namespaces and pass vacuously until real domain code exists — when adding a bounded context (e.g. `App\<Context>\Domain`), extend these checks rather than assuming they already cover it.

### Static analysis and style — estat actual

- PHPStan runs at `level: max` with `missingCheckedExceptionInThrows` enabled (`phpstan.dist.neon`) — any method throwing a custom exception must document it with `@throws`, checked recursively through call chains.
- CS Fixer applies `@PER-CS` + `@Symfony` plus `declare_strict_types`, snake_case PHPUnit/Pest method names, and keeps `throw` as its own statement (`single_line_throw: false`) (`.php-cs-fixer.dist.php`). It scans `config/`, `public/`, `src/`, `tests/`, excluding `reference.php`.

This is the single source of truth for the *current* PHPStan/CS Fixer configuration. See
"Convencions" below for the target/plan, which differs from this current state on purpose (not yet
applied).

# KAL App — Context del projecte

## Stack (decidit, no reobrir sense motiu)
- **Backend:** PHP 8.5 + Symfony 8 + **Doctrine DBAL amb SQL directe**
  (NO Doctrine ORM: els agregats es reconstrueixen a mà als repositoris). Pujat
  des de PHP 8.3 + Symfony 7 a la branca `KAL-001`: `doctrine/doctrine-bundle`
  `^2.18` no suportava Symfony 8, es va pujar a `^3.0`
- **Frontend:** Vue 3 + Vite + **TypeScript**, PWA responsive (no app nativa)
- **BD/Auth/Storage:** Supabase (Postgres + magic links + buckets privats)
- **Emails:** Resend (via symfony/mailer amb el bridge de Resend)
- **Cron/async:** Symfony Messenger + Scheduler (transport doctrine per la cua;
  sense Kafka/RabbitMQ a l'MVP — sobredimensionat per una app d'aquesta mida)
- **ULIDs:** symfony/uid (`Ulid::generate()`) a l'aplicació, mai a la BD
  (excepció documentada a Regles transversals)
- **Local:** PHP-FPM + Caddy (o symfony CLI server) en Docker (compose amb el
  codi muntat com a volum); Supabase via CLI (`supabase start`, contenidors
  propis fora del compose). PHP hi arriba per `host.docker.internal`
- **Migracions:** `supabase migration new` + `supabase db push` (esquema
  versionat al Git; les migracions són SQL pur, cap dependència de Doctrine
  Migrations)
- **Tipat compartit:** OpenAPI generat amb NelmioApiDocBundle (attributes als
  controllers) → tipus TS del frontend amb openapi-typescript
- **Hosting previst:** backend Fly.io/Railway (des del Dockerfile), frontend
  Netlify/Vercel
- **i18n des del principi:** català i castellà (vue-i18n); anglès a Fase 2.
  Cap text hardcoded a la UI

## Arquitectura de permisos (important!)
- El frontend parla **directament** amb Supabase per auth (supabase-js, magic
  links) i pujada de fotos; la seguretat la fan les **polítiques RLS** ja creades
- Symfony (amb la service_role key, salta RLS) només per la lògica que no pot
  viure al client: validar tokens d'invitació, emails/recordatoris, crons,
  URLs signades de fotos
- Verificació del JWT de Supabase al backend via JWKS (authenticator propi de
  Symfony Security o lcobucci/jwt + firebase/php-jwt — decidir a la primera
  implementació i documentar-ho aquí)
- Fotos: bucket **privat**, servides amb URLs signades. Compressió **al client**
  abans de pujar (browser-image-compression, ~300 KB objectiu)

## Emmagatzematge de recursos (regla: binaris a Storage, enllaços/text a Postgres)
- Bucket `kal-photos` (privat) — fotos de progrés (`{kal_id}/{clue_id}/{photo_id}.webp`),
  imatges del debat (`{kal_id}/debat/{message_id}.webp`), portades
  (`{kal_id}/portada.webp`). Tot passa per la mateixa pipeline de compressió al client
- Bucket `kal-patterns` (privat, SEPARAT) — PDFs de patrons i pistes
  (`{kal_id}/{clue_id}/pista.pdf`). Separat perquè és el bé més sensible (por a
  la pirateria de patrons a la comunitat): polítiques pròpies, URLs signades de
  vida molt curta. `clues.pattern_path` guarda el **path** de Storage, no una
  URL pública
- `pattern_images` (taula, dins `kal-photos`) — fotos de la fitxa tècnica del
  patró (`{kal_id}/patro/{img}.webp`): esquemes, mostres de teixit, detalls...
  Llista ordenada (`position`) part de l'agregat Pattern (no de `kal-patterns`:
  no són el PDF en si, són material il·lustratiu, mateix règim que la resta de
  fotos)
- `pattern_versions` (taula, dins `kal-patterns`) — el PDF del patró pot tenir
  diverses versions traduïdes (`{kal_id}/{locale}.pdf`); no hi ha un "PDF
  principal" independent de l'idioma
- Vídeos: MAI allotjar-ne. Només enllaços YouTube/Vimeo a `kal_videos.url`
- Postgres només guarda metadades i paths, mai binaris

## Pistes / MKAL (decidit; UI a Fase 2)
- Les `clues` són les pistes. Cada clue té `pattern_path` (PDF propi) i
  `starts_on` (alliberament programat)
- Abans de `starts_on`, el **contingut de la pista** (PDF, vídeos gravats i
  reunions visuals lligades a la pista) NO és visible per a participants —
  reforçat a RLS (helper `is_clue_released`) i a les URLs signades de Storage,
  no només a la UI. NO afecta el xat: el xat és d'aula (nivell KAL), no de pista
- **No existeix el "debat per pista".** El xat escrit és sempre d'aula a nivell
  de KAL (veure Reunions i aules més avall)

## Model de domini (DDD — decidit, veure supabase/migrations/)
**Context `src/Users/` — identitat:** perfil neutre (id intern ULID, external_id
d'auth Supabase, display_name, avatar, locale). SENSE rol: una persona no "és"
organitzadora o participant. El rol és **contextual per KAL** i es deriva de les
relacions (`kals.organizer_id`, `participations`) — mai una columna `role`. La
mateixa persona pot ser organitzadora d'un KAL i participant d'un altre. "El
client és l'organitzadora" = la persona quan actua com a organitzadora.

**Agregat Kal (arrel, `src/Kals/`):** id ULID · organizerId · name/description ·
createdAt, updatedAt, startsOn, endsOn?, deletedAt (soft delete) · coverPath? ·
pinnedMessage · inviteToken
- **Clue (pista)** — ENTITAT dins l'agregat: id ULID, name, description,
  patternPath? (PDF propi de la pista), startsOn (alliberament), endsOn,
  updatedAt. Invariant a l'arrel: les dates de cada clue cauen dins del rang
  del Kal. Un Kal es pot crear buit
- **PatternInfo (fitxa tècnica)** — dins l'agregat Kal durant l'MVP (NO context
  propi encara). Camps a taula filla + jsonb: craft ('knitting'/'crochet', per
  defecte 'knitting'), garmentType?, yarnWeight?, difficulty?, patternInfo jsonb
  (sizes, finishedMeasurements, yardage, yarnsShownIn, needles, hook, notions[],
  gauge, techniques[] — tots opcionals), versions traduïdes del PDF (locale +
  patternPath) i imatges (storagePath + position). Es completa progressivament
  amb `PATCH`. Kal i fitxa es creen a la MATEIXA transacció al `CreateKalHandler`
  (sense event ni listener). **Dissenyat per esdevenir bounded context propi a
  la Fase Patterns** (venda + comissions + discoverable); l'esquema (mateixes
  taules i columnes) ja ho preveu, l'extracció serà neta
- **Reunions visuals (`Meeting`)** — entitat {id ULID, scheduledAt, url, title,
  timezone, clueId?}: trobades en directe (Zoom/Meet, enllaç extern). `clueId`
  null = reunió del KAL; informat = reunió d'una pista concreta. Poden ser
  vàries (típicament per idioma). `timezone` (per defecte 'Europe/Madrid')
  perquè scheduledAt és hora local de l'organitzadora. MVP: només a nivell de
  KAL. Fase 2: reunions lligades a pista + múltiples per idioma
- **Aules de xat (`debate_rooms`)** — xat escrit asíncron, SEMPRE a nivell de
  KAL, mai per pista. Poden ser vàries (típicament per idioma). Els missatges
  (`debate_messages`) NO tenen clueId. L'organitzadora hi participa quan pot;
  les participants es responen entre elles. Persistència: relació
  `kal_debate_rooms`. Flag `hidden` als missatges per moderació. MVP (candidat,
  veure Funcionalitats): 1 sola aula. Fase 2: múltiples per idioma
- **VideoLink** — value object {url, title, position}, opcionalment lligat a
  una clue (vídeos gravats/tutorials incrustats, YouTube/Vimeo). `url` sempre
  `https://` (mai allotjar vídeo propi)
- Participation, ClueProgress, Photo (photos amb flag hidden per moderació)

**Regles transversals:**
- ULIDs (text de 26 chars) generats A L'APLICACIÓ amb symfony/uid, mai a la BD.
  Excepció: `profiles.id` es genera en SQL (`generate_ulid()`) perquè el crea
  el trigger `handle_new_user()` sense accés a l'aplicació. `profiles.id` NO és
  l'uuid de Supabase Auth — aquest queda a `profiles.external_id`, desacoblat
  (migració `20260716190425_users_external_id_ulid.sql`; `organizer_id`/
  `user_id` sempre usen l'id intern, mai `auth.uid()` directament)
- Soft delete a kals via deleted_at; les RLS ja exclouen esborrats
- El rol es deriva del context, mai s'emmagatzema: `is_kal_organizer` (ets
  `kals.organizer_id`), `is_kal_member` (participació activa o ets l'organizer)
- Al backend, DDD amb hexagonal: domini pur (sense Symfony/DBAL) separat
  d'infraestructura; sense sobre-arquitecturar l'MVP. Composició (p.ex.
  `KalResponse` amb la fitxa incrustada perquè el FE no faci una segona crida)
  es fa a la capa `Ui/`, no al repositori

## Abast per fases del model
- **MVP** exposa a la UI: Kal + Clues bàsiques + PatternInfo (fitxa progressiva,
  dins de Kal, sense cercador) + Participation + Photos + reunions visuals a
  nivell de KAL + 1 aula de xat (candidata, pendent d'entrevistes)
- **Fase 2:** múltiples aules de xat per idioma, vídeos gravats per pista,
  alliberament programat amb recordatoris, reunions visuals lligades a pista +
  múltiples per idioma
- **Fase Patterns (post-MVP):** extreure PatternInfo al seu bounded context
  propi `src/Patterns/` (agregat, repositori, event de creació). Habilita la
  venda de patrons a través del producte (amb descompte a l'organitzadora que
  hi ven) i `visibility='discoverable'` per al cercador/recomanador. L'esquema
  de l'MVP ja ho preveu; l'extracció serà neta
- **Fase IA (línia pròpia, post-MVP):** assistent de creació per a
  l'organitzadora — llegir el PDF del patró per pre-omplir PatternInfo, suggerir
  descripcions i estructura de pistes, detectar llanes/gruixos. Línia
  diferenciada del recomanador, tot i compartir el corpus de patrons
  estructurats. El MCP de llanes/patrons (read-only) hi connecta

## Funcionalitats MVP (i NOMÉS aquestes)
1. CRUD de KALs i pistes (organitzadora)
2. Apuntar-se via enllaç d'invitació + login màgic (objectiu: <30 s des del mòbil)
3. Pujar fotos per pista + galeria comuna del KAL
4. Recordatoris per email (inici de ronda; trobada 30 min abans)
5. Reunions visuals = enllaç extern (Zoom/Meet) + horari, a nivell de KAL (poden
   ser vàries). NO vídeo integrat; les lligades a pista i el multi-idioma són Fase 2
6. Moderació: organitzadora amaga fotos i expulsa participants
7. Mètriques de validació: % participants amb ≥2 fotos, retorn setmanal, 2n KAL creat

**Candidata a entrar a l'MVP (decisió pendent de les entrevistes):** xat d'aula
única per KAL (text + foto, Supabase Realtime, inserts directes amb RLS —
gairebé sense backend). La tesi de la desenvolupadora és que sense conversa un
KAL no viu; es valida a les entrevistes abans de construir-lo.

**Fora de l'MVP (no implementar encara que sembli fàcil):** gamificació,
comentaris/reaccions, integracions Instagram/Discord, push notifications,
pagaments (Stripe), vídeo integrat (Daily.co/Jitsi), venda de patrons, Pattern
com a context separat (Fase Patterns), múltiples aules de xat per idioma,
reunions lligades a pista, assistent de creació amb IA (Fase IA), recomanador.

## Convencions
- Estructura backend: **hexagonal + CQRS per bounded context** (no per capa
  tècnica). Contexts de l'MVP: `src/Users/`, `src/Kals/` (i `src/Patterns/`
  arribarà a la Fase Patterns). Cada context té:
    - `Domain/` — entitats, value objects i invariants (PHP pur, sense Symfony ni
      DBAL) i el port: una **interface** del repositori (p.ex.
      `KalRepositoryInterface`)
    - `Application/` — Commands (canvien estat) i Queries (només lligen) com a
      missatges + handlers, despatxats pels **busos de Symfony Messenger**
      (command.bus / query.bus / event.bus, transport sync a l'MVP)
    - `Infrastructure/` — adaptadors: `DbalKalRepository` (SQL directe amb
      Doctrine DBAL, reconstrucció manual de l'agregat, transaccions explícites)
    - `Ui/` — controllers de Symfony: tradueixen HTTP ↔ Commands/Queries via el
      bus, mai toquen SQL ni instancien handlers
    - `src/Shared/` — transversal: connexió DBAL, autenticació JWT (JWKS de
      Supabase), busos, events de domini entre contexts, logging
- **Qualitat — estat actual vs. objectiu:** l'estat *actual* és `phpstan.dist.neon`
  amb `level: max` (veure "Static analysis and style" a Architecture, font de
  veritat de la configuració real); l'**objectiu** és baixar-ho a `level: 9`
  amb baseline buida, encara **no aplicat**. CS Fixer aplica `@PER-CS` +
  `@Symfony` (`.php-cs-fixer.dist.php`), no PSR-12 sol. A banda d'això:
  strict_types=1 a tot arreu, readonly i tipats exhaustius. Deptrac (o test
  d'arquitectura equivalent) ha de fer complir que `Domain/` no depèn de
  Symfony/DBAL ni de `Infrastructure/`/`Ui/` del seu context — encara no
  configurat (veure "Agent Harness" més avall)
- **Tests:** PHPUnit; els d'integració contra Supabase **local** (mai producció)
- **Principi d'API multi-client:** el backend exposa contractes per context
  (OpenAPI) pensats perquè hi hagi múltiples clients — avui el frontend Vue
  (lectura+escriptura, JWT d'usuària) i demà el MCP de llanes/patrons (només
  lectura, token de servei). No dissenyar l'API assumint un únic client
- Secrets NOMÉS a `.env.local` (mai al Dockerfile ni al Git)
- Comentaris i textos d'UI: català. Codi (noms, classes): anglès
- RGPD: consentiment al registre, dret a esborrat de compte i dades, fotos
  visibles només per membres del KAL

## Agent Harness
**Pla pendent, no implementat encara.** Les comandes següents són l'objectiu
per al gate de qualitat de qualsevol agent (humà o IA); avui el `Makefile`
només té les comandes reals llistades a `## Commands` (`make qa`,
`make run-phpstan`, `make run-cs-fixer`, `make run-arch`, `make test`, entre
d'altres) — cap de `make lint` / `make typecheck` / `make arch` / `make check`
existeix encara. No citar-les com si funcionessin avui; construir-les és una
tasca d'implementació separada.

Pla previst:
- `make lint` (php-cs-fixer --dry-run)
- `make typecheck` (phpstan analyse, level 9)
- `make arch` (deptrac o equivalent)
- `make test` (phpunit contra Supabase **local**)
- Tot junt: `make check`

**No hi ha CI encara** — mentre el pla anterior no estigui construït, l'únic
gate real en local abans de comitejar és `make qa` (veure `## Commands`).
Limitació coneguda de l'MVP, no una decisió definitiva.

### Convencions no negociables sense motiu
- Conventional Commits (`feat:`, `fix:`, `refactor:`...)
- Errors com a codis (`kal_not_found`), mai frases per humans — la traducció
  viu al frontend
- Injecció de dependències via el container de Symfony (autowiring); un
  controller mai instancia un handler a mà — tot passa pel bus
- Els PRs/canvis els revisa la desenvolupadora amb criteri propi: canvis
  petits i explicables, no megacommits

### Errors ja comesos, no repetir (heretats de la fase Python — el patró aplica)
- `repo->add()` ha de fer `INSERT ... RETURNING` i tornar l'entitat persistida,
  no l'objecte en memòria (`created_at` quedava null → 500 real)
- Logging estructurat: assegurar que el context/extra dels logs s'imprimeix de
  veritat (configurar el formatter de Monolog, no confiar en el per defecte)
- En tests d'integració amb FKs: l'ordre de neteja dels fixtures importa

## Context de negoci (per decisions de producte)
- Validar primer gratis amb 1-2 organitzadores "fundadores"; monetitzar
  (subscripció 10-15 €/mes per organitzadora) quan hi hagi 20-30 actives
- Objectiu personal: ~500 €/mes d'ingressos extra
- La desenvolupadora és docent de la Generalitat: cal compatibilitat aprovada
  abans de generar ingressos (tràmit pendent, no bloqueja el desenvolupament)
- Via paral·lela futura: MCP de llana/patrons com a canal d'adquisició. És un
  **client read-only** de l'API del producte (només GET, mateix contracte
  OpenAPI): consulta patrons/KALs `discoverable` i dades estructurades per
  recomanar/respondre. No escriu MAI, cap acció en nom de l'usuària; auth de
  lectura amb token de servei, sense accés a contingut privat d'altri. Hi ha SDK
  de MCP per PHP; si convingués fer-lo en Python, és un mòdul petit i aïllat
- El pla complet de tasques és a Notion ("Eina KALs — Tauler de tasques")
