# CLAUDE.md cleanup

> Status: **COMPLETAT** (verificat el 2026-07-29). Els nou criteris d'acceptació
> es compleixen contra el `CLAUDE.md` actual: introducció única com a backend de
> KAL App, secció "Adapting this boilerplate" eliminada, cap secció duplicada,
> PHPStan `level: max` (actual) vs `level: 9` (objectiu, no aplicat) diferenciats,
> CS Fixer com a `@PER-CS` + `@Symfony`, i "Agent Harness" marcat com a pla
> pendent. El contingut de negoci i domini es manté intacte.
>
> Matís sobre l'últim criteri ("cap altre fitxer del repo es modifica"):
> `composer.json` ja no es diu `you/php-challenge` sinó `laurabcn/kal-meet-app`,
> però va ser el commit `d7a57c3`, feina a part, no un efecte d'aquesta neteja.
>
> Aquest document queda com a **registre del que es va fer**, no com a encàrrec
> obert. La descripció del problema de sota descriu l'estat *anterior*.

## Problema

`CLAUDE.md` conté avui dos blocs superposats sense reconciliar:

1. El bloc original del boilerplate (línies 1–80 aprox.), que descriu el repo
   com "a reusable, Dockerized starting point for PHP take-home /
   technical-challenge exercises" i inclou una secció "Adapting this
   boilerplate for a new challenge" (renombrar `composer.json`, decidir si cal
   HTTP o no, etc.) — llenguatge de plantilla genèrica, no del projecte KAL.
2. El bloc "KAL App — Context del projecte" (afegit, encara sense commitejar),
   que descriu el producte, l'stack, el model de domini i les convencions
   reals del projecte KAL.

Els dos blocs es contradiuen en punts concrets ja verificats contra el repo:

- **PHPStan:** el boilerplate diu "level: max" (cert — `phpstan.dist.neon` té
  `level: max`); el bloc KAL diu "level 9 (baseline buida)". No hi ha
  baseline configurada. `max` i `9` no són necessàriament el mateix llindar
  segons la versió de PHPStan instal·lada.
- **Comandes de qualitat:** el boilerplate documenta `make qa` (que executa
  `run-phpstan` + `run-cs-fixer` + `test`) i `make run-phpstan` /
  `make run-cs-fixer`, que són les que existeixen de debò al `Makefile`. El
  bloc KAL ("Agent Harness") parla de `make lint`, `make typecheck`,
  `make arch` i `make check`, cap de les quals existeix avui al `Makefile`
  (verificat: només hi ha `qa`, `run-phpstan`, `run-cs-fixer`,
  `run-cs-fixer-fix`, `test`, `run-pest`, `run-arch`, `run-tests-filter`,
  `run-tests-retry`, `setup`, `build`, `composer-install`, `composer-require`,
  `composer-require-dev`, `bash`, `serve`, `down`).
- **Estat real del codi:** `composer.json` encara es diu `you/php-challenge`
  i `src/` només conté `Controller/`, `Kernel.php` i `Shared/` — cap de
  `src/Users/` ni `src/Kals/` existeix encara, i no hi ha configuració de
  Deptrac. El bloc KAL els descriu com si el disseny ja estigués fixat, cosa
  certa a nivell de decisió però no reflectida en cap comentari sobre l'estat
  d'implementació.

Un agent (humà o IA) que llegeixi `CLAUDE.md` d'entrada no pot distingir què
és decisió vigent, què és narrativa obsoleta del boilerplate, i què és pla
encara no construït — amb risc de citar comandes que no funcionen o de seguir
instruccions "d'adaptar el boilerplate" que ja no apliquen.

## Objectius / No-objectius

### Objectius

- Reescriure la introducció de `CLAUDE.md` perquè presenti el repo com el
  backend real del projecte KAL, no com una plantilla genèrica de
  "take-home / technical-challenge exercises".
- Eliminar (o reduir a una nota històrica molt breu, si val la pena
  conservar-la) la secció "Adapting this boilerplate for a new challenge" —
  ja no hi ha cap "challenge" a adaptar.
- Conservar i integrar en la narrativa única totes les parts del bloc
  boilerplate que segueixen sent certes i útils: les comandes `make` reals
  (`setup`, `qa`, `run-phpstan`, `run-cs-fixer`, `test`, `run-arch`, etc.), la
  documentació del `Shared` kernel (busos, `AggregateRoot`, value objects), i
  la descripció dels arch tests (`tests/Arch`).
- Resoldre explícitament el conflicte PHPStan `level: max` vs `level 9`: el
  text ha de deixar clar quin és l'estat **actual** (`level: max`, segons
  `phpstan.dist.neon`) i quin és el **pla** (moure a `level 9` amb baseline
  buida), sense presentar el pla com si ja estigués fet.
- Marcar explícitament com a **pla pendent, no implementat encara** les
  comandes `make lint` / `make typecheck` / `make arch` / `make check` de la
  secció "Agent Harness" — avui el `Makefile` només té `qa` / `run-phpstan` /
  `run-cs-fixer` / `run-arch` (via Pest) / `test`. El text no s'ha de llegir
  com si aquestes comandes ja funcionessin.
- Deixar un únic document sense seccions duplicades ni contradictòries, on
  quede clar què és decisió vigent i què és treball futur.

### No-objectius

- No crear ara les comandes `make` que falten (`lint`, `typecheck`, `arch`,
  `check`) ni tocar `Makefile`/`phpstan.dist.neon`/`composer.json` — això és
  una tasca d'implementació separada; aquest spec només documenta l'estat i
  el pla dins de `CLAUDE.md`.
- No revisar ni retocar el contingut del bloc "KAL App — Context del
  projecte" que ja és correcte i no té conflicte (model de domini, fases,
  context de negoci, etc.) — es manté tal qual, només es reorganitza si cal
  per eliminar la duplicitat amb el bloc boilerplate.
- No canviar cap altre fitxer del repo.

## Canvis de contingut

Mapa secció-per-secció de l'estructura actual (línies de referència de
`CLAUDE.md` a hores d'ara) cap a l'estructura final:

| Secció actual | Acció |
|---|---|
| `## What this is` (L5) | **Reescriure.** Substituir la descripció "reusable, Dockerized starting point for PHP take-home / technical-challenge exercises" per una descripció del backend real de KAL App, fusionant-hi el contingut encara vàlid de `## Què és` (L84). Un únic bloc d'introducció, no dos. |
| `## Commands` (L9) | **Mantenir sense canvis.** Ja reflecteix el `Makefile` real i no té conflicte. |
| `## Adapting this boilerplate for a new challenge` (L33) | **Eliminar.** Ja no hi ha cap challenge a adaptar; res d'aquesta secció aplica al projecte KAL. |
| `## Architecture` i subseccions (L41–82) | **Mantenir**, és documentació tècnica vigent del kernel `Shared` i els arch tests. Sense canvis de contingut. |
| `### Static analysis and style` (L77, dins Architecture) | **Afegir una nota de reconciliació**: aquesta secció ja diu correctament "level: max" i "@PER-CS + @Symfony" (verificat contra `phpstan.dist.neon` i `.php-cs-fixer.dist.php`) — cal que sigui l'única font de veritat sobre l'estat *actual*, i que la secció `Convencions` (KAL) no en doni una versió diferent (veure fila següent). |
| `## Què és` (L84) | **Fusionar** dins la nova `## What this is` (veure primera fila) i eliminar com a secció separada. |
| `## Stack` … `## Funcionalitats MVP` (L113–272) | **Mantenir sense canvis de contingut** — cap conflicte detectat amb el bloc boilerplate. |
| `## Convencions` (L274) | **Corregir dues afirmacions incorrectes verificades**: (1) "PHPStan level 9 (baseline buida)" → ha de dir explícitament que l'estat *actual* és `level: max` (real) i que `level 9` és l'objectiu/pla, encara no aplicat; (2) "PHP CS Fixer amb PSR-12" → el ruleset real és `@PER-CS` + `@Symfony` (`.php-cs-fixer.dist.php`), no PSR-12 sol — corregir la referència. La resta de la secció (Deptrac, tests, principi API multi-client, secrets, idioma, RGPD) es manté. |
| `## Agent Harness` (L304) | **Marcar explícitament com a pla, no com a estat actual.** Afegir una frase inicial del tipus "Aquestes comandes són l'objectiu; avui només existeixen `make qa` / `make run-phpstan` / `make run-cs-fixer` / `make run-arch` / `make test` (veure `## Commands`)." Mantenir la llista `make lint`/`make typecheck`/`make arch`/`make check`/`make check` com a TODO explícit, no com a fet consumat. |
| `### Convencions no negociables sense motiu` (L316) | **Mantenir sense canvis.** |
| `### Errors ja comesos, no repetir` (L325) | **Mantenir sense canvis.** |
| `## Context de negoci` (L332) | **Mantenir sense canvis.** |

Resultat final: un `CLAUDE.md` amb una sola introducció (projecte KAL real),
sense la secció d'adaptació de boilerplate, amb les dues discrepàncies
PHPStan/CS-Fixer corregides i la secció Agent Harness marcada clarament com a
pla pendent en lloc d'estat actual.

## Criteris d'acceptació

- [ ] `CLAUDE.md` té una única secció introductòria que descriu el repo com el
      backend de KAL App; no queda cap referència a "take-home / technical-challenge
      exercises" ni a "boilerplate" com a marc narratiu principal.
- [ ] La secció "Adapting this boilerplate for a new challenge" ha
      desaparegut.
- [ ] No hi ha cap secció duplicada (p.ex. no hi ha dues seccions "Què és"/
      "What this is").
- [ ] La descripció de PHPStan indica sense ambigüitat: nivell actual
      (`level: max`, segons `phpstan.dist.neon`) i nivell objectiu (`9`, encara
      no aplicat) com dues coses diferents.
- [ ] La descripció de CS Fixer diu `@PER-CS` + `@Symfony` (no "PSR-12"),
      coherent amb `.php-cs-fixer.dist.php`.
- [ ] La secció "Agent Harness" deixa clar, en una frase inicial o nota, que
      `make lint` / `make typecheck` / `make arch` / `make check` són un pla
      pendent i que les comandes reals disponibles avui són les llistades a
      `## Commands`.
- [ ] Cap altre fitxer del repo (`Makefile`, `phpstan.dist.neon`,
      `.php-cs-fixer.dist.php`, `composer.json`) es modifica com a part
      d'aquest treball.
- [ ] El contingut de negoci/domini/fases (Stack, Arquitectura de permisos,
      Emmagatzematge, Pistes/MKAL, Model de domini, Abast per fases,
      Funcionalitats MVP, Convencions no negociables, Errors ja comesos,
      Context de negoci) es manté intacte tret dels dos punts de correcció
      indicats a "Canvis de contingut".
- [ ] El fitxer resultant es pot llegir de dalt a baix sense trobar cap
      contradicció entre seccions.

## Fora d'abast

- Crear les comandes `make lint` / `make typecheck` / `make arch` /
  `make check` al `Makefile`. Aquest spec només documenta que són un pla
  pendent; construir-les és una tasca d'implementació separada (probablement
  via `/team-lead` un cop hi hagi codi de domini real a `src/Users` o
  `src/Kals` que justifiqui tenir `make arch` amb Deptrac).
- Canviar `phpstan.dist.neon` de `level: max` a `level: 9` o afegir-hi
  baseline.
- Renombrar `composer.json` (`"name": "you/php-challenge"`) ni el namespace
  `App\`.
- Configurar Deptrac.
- Qualsevol canvi al contingut de domini/negoci del bloc KAL que no sigui una
  de les dues correccions PHPStan/CS-Fixer ja identificades.
- Reorganitzar `CLAUDE.md` en múltiples fitxers (p.ex. separar contingut de
  negoci vs. tècnic) — es manté com un únic fitxer, només reordenat/depurat.

## Riscos i preguntes obertes

**Riscos:**
- En eliminar la secció "Adapting this boilerplate", es perd la guia de com
  despullar les parts HTTP-only si mai calgués tornar a fer servir aquest
  repo com a plantilla per a un altre challenge. Assumit com a acceptable:
  el repo ja és el producte real, no una plantilla reutilitzable.
- En fusionar "What this is" + "Què és" en una sola introducció, hi ha risc
  de perdre matisos si es fa massa de pressa; cal revisar-ho amb cura en la
  redacció final, no només retallar i enganxar.

**Decidit:**
1. El pla "Agent Harness" queda només com a nota dins `CLAUDE.md`; no cal
   duplicar-ho com a tasca a Notion.
2. Aquest canvi es commiteja juntament amb el contingut KAL, que encara no
   s'havia commitejat mai — un sol commit inicial ja net i sense contradiccions
   (no cal separar-ho en "afegir KAL" + "netejar KAL").
