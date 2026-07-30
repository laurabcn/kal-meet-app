# Spec: contracte HTTP d'escriptura — resposta de CreateKal i mapatge d'excepcions

> Status: **PENDENT — branca futura.** Res d'això és a `KAL-002`; s'hi va decidir
> deixar-ho fora expressament per no barrejar el cablejat HTTP del primer
> endpoint amb decisions de contracte que afecten tots els endpoints següents.
> Escrit el 2026-07-29 des de l'estat real de la branca `KAL-002`.

---

## 1. Context — què hi ha avui

`KAL-002` deixa la primera vertical d'escriptura sencera i funcionant:

```
POST /kal → KalCreateController → command.bus → CreateKalHandler → DbalKalRepository
```

- `src/Kal/UI/Http/KalCreateController.php` — llegeix el JSON del body, valida
  la **forma** del payload i despatxa `CreateKalCommand` pel port
  `CommandBusInterface`.
- `config/routes.yaml` i `config/services.yaml` — registren el directori de
  controllers del context (`../src/Kal/UI/Http/`). Cada context nou s'hi ha
  d'afegir a mà; està documentat a `CLAUDE.md` (secció Convencions → `UI/`).
- `config/services.yaml` — els binds dels quatre busos són **per tipus + nom**
  (`MessageBusInterface $commandBus: '@command.bus'`). Un bind només per nom
  segrestava també els paràmetres tipats amb els ports de `Shared/Application`.

Comportament actual verificat per HTTP real:

| cas | resposta |
| --- | --- |
| body no és JSON | `400 {"error":"kal_invalid_json"}` |
| falta un camp obligatori / té el tipus equivocat | `400 {"error":"kal_invalid_payload"}` |
| payload correcte | `201` **sense body** |
| invariant de domini violada (dates, locales, fitxers…) | `500` (excepció sense mapar) |

Els dos últims casos són el que aquesta spec ha de resoldre.

---

## 2. Goals

- Definir què retorna una operació d'escriptura quan té èxit (i concretament
  si retorna l'id del recurs creat).
- Definir com es tradueixen les excepcions de domini a respostes HTTP, **una
  sola vegada per a tot el backend**, no amb un `try/catch` per controller.
- Deixar el contracte prou tancat perquè el frontend Vue i el futur MCP de
  llanes hi puguin generar tipus (OpenAPI) sense sorpreses.

## 3. Non-Goals

- Autenticació. L'`organizerId` que avui ve al body és un tema seu (§6).
- Mutacions del KAL (rename, canvi de dates, soft delete per API) ni cap
  endpoint de lectura.
- NelmioApiDocBundle / generació d'OpenAPI: se'n beneficia, però no és
  aquesta tasca.

---

## 4. Decisió 1 — la resposta de `CreateKal`

### Problema

`Kal::create()` genera l'ULID **a dins de l'agregat** i `CreateKalHandler`
retorna `void`, així que el controller no té l'id per retornar-lo. Ara mateix
respon `201` amb el body buit. Si el frontend ha de redirigir al KAL acabat de
crear (que és el flux natural: crear → anar-hi), li cal l'id i avui no el pot
obtenir sense una segona query.

### Opcions

**A. Generar l'ULID a la capa Application i passar-lo dins de la comanda.**
El controller (o el handler) crea l'`UlidValue`, el posa a `CreateKalCommand`,
i qui l'ha generat ja el té per respondre. Encaixa amb la regla del projecte
("ULIDs generats a l'aplicació, mai a la BD") i manté el command handler amb
retorn `void`, que és el que demana el patró CQRS estricte.

**B. Que el handler retorni l'id.** Més directe, però trenca la regla que un
command handler no retorna res, i el bus (`CommandBusInterface::dispatch()`)
avui és `void` — caldria tocar el port compartit.

**Recomanació: A.** No toca `Shared`, respecta CQRS i no obliga a canviar la
signatura del bus.

### Qüestions per decidir

- Qui genera l'ULID: el controller o el handler? (Si el genera el controller,
  la comanda porta un id ja fet i el handler és 100 % determinista, cosa que
  simplifica els tests.)
- Format de la resposta: `201` + `{"id": "..."}`, o `201` + capçalera
  `Location: /kal/{id}` (més REST, però encara no existeix `GET /kal/{id}`).
- El body de `201` ha d'incloure alguna cosa més (el `inviteToken`, per
  exemple, que l'organitzadora necessitarà de seguida per convidar)?

---

## 5. Decisió 2 — mapatge d'excepcions de domini a HTTP

### Problema

`KalException` ja llença **codis** llestos per al frontend
(`kal_invalid_date_range`, `kal_clue_outside_range`, `kal_no_locales_enabled`,
`kal_file_locale_not_enabled`, `kal_meeting_invalid_timezone`,
`kal_persistence_failed`…) i `InvalidArgumentException` de `Shared` en llença
uns quants més. Cap arriba al client: pugen fins al kernel i es converteixen
en `500`. El client no pot distingir "has enviat dates incoherents" de "el
servidor ha petat".

### Proposta

Un **listener de `kernel.exception`** a `src/Shared/Infrastructure/`, no un
`try/catch` a cada controller (repetir-lo per endpoint és exactament el que
`CLAUDE.md` vol evitar, i escala malament amb 15 endpoints).

Mapatge de partida:

| excepció | status | body |
| --- | --- | --- |
| `App\Shared\Domain\Exception\DomainException` (i filles: `KalException`, `KalFileException`) | `400` | `{"error": "<codi>"}` (el `getMessage()` **ja és** el codi) |
| `App\Shared\Domain\Exception\InvalidArgumentException` | `400` | `{"error": "invalid_argument"}` — **veure avís sota** |
| qualsevol altra | `500` | `{"error": "internal_error"}`, amb el detall només al log |

### Avís important sobre `InvalidArgumentException`

Els seus missatges **no són codis**: són frases en anglès per a humans
(`'The value cannot be negative'`, `'The date time does not match the expected
format "%s"'`). Barrejats amb els de `KalException`, que sí que són codis. Si
el listener fa `getMessage()` sobre tot indiscriminadament, el frontend rebrà
prosa anglesa impossible de traduir — i això contradiu la convenció "errors com
a codis, mai frases per humans".

Dues sortides: (a) el listener només exposa el missatge de `DomainException` i
per a `InvalidArgumentException` retorna un codi genèric; (b) es converteixen
tots els missatges d'`InvalidArgumentException` a codis
(`value_negative`, `invalid_datetime_format`…) i llavors s'exposen igual que
els altres. **(b) és més feina però deixa el contracte coherent**; alguns ja hi
són a mig camí (`file_size_not_positive`, `invalid_url`, `url_must_be_https`).
Cal decidir-ho abans d'escriure el listener, no després.

### Qüestions per decidir

- Tot `DomainException` és `400`, o hi ha famílies que han de ser `404`
  (`kal_not_found`, quan existeixi) i `409` (conflictes)? Probablement caldrà
  una manera que l'excepció digui el seu status, o un mapa explícit al listener.
- `kal_persistence_failed` és un error de servidor, no del client: hauria de
  ser `500` encara que sigui una `KalException`. Cas especial a tenir present.
- El body d'error, porta només `error`, o també un `field` quan l'error és de
  payload? El controller avui pot dir quin camp ha fallat però no ho exposa.

---

## 6. Relacionat, però d'una altra branca: `organizerId`

`POST /kal` llegeix `organizerId` **del body**. Sense autenticació, qualsevol
pot crear un KAL a nom de qui vulgui. Quan entri la verificació del JWT de
Supabase (JWKS), l'`organizerId` ha de sortir del token i **desaparèixer del
payload**; el camp del body passa a ser ignorat o rebutjat.

No és part d'aquesta spec, però sí un motiu per no donar el contracte de
`POST /kal` per tancat de cara al frontend fins que això estigui fet.

---

## 7. Criteris d'acceptació

- [ ] `POST /kal` amb payload vàlid retorna `201` i l'id del KAL creat, en el
      format decidit a §4.
- [ ] Una invariant de domini violada (p.ex. `endsOn` anterior a `startsOn`)
      retorna `400` amb el codi corresponent, no `500`.
- [ ] Un error inesperat retorna `500` amb un codi genèric i **cap** detall
      intern al body; el detall queda al log.
- [ ] Cap controller té un `try/catch` d'excepcions de domini: el mapatge viu
      en un sol lloc.
- [ ] Hi ha tests funcionals que cobreixen les tres respostes anteriors passant
      pel kernel HTTP real, no només pel handler.
- [ ] `make qa` no afegeix errors nous a `KalCreateController` ni al listener.
