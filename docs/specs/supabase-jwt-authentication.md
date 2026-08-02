# Spec: autenticació — verificació del JWT de Supabase i usuària autenticada

> Estat: **complet**. La signatura del JWT està decidida (§11: JWKS, claus
> asimètriques); queda per confirmar l'algorisme concret de les claus, que
> afecta la implementació interna del `TokenHandler` però no el disseny.
>
> Relacionat: [`kal-http-response-and-errors.md`](./kal-http-response-and-errors.md) §6,
> que ja anticipa que l'`organizerId` ha de sortir del token. Aquell spec és
> responsable del mapatge d'excepcions de **domini**; aquest, només dels errors
> d'**autenticació**.

---

## 1. Problem

`POST /kal` llegeix l'`organizerId` **del cos de la petició**
(`KalCreateController.php:48`) i la ruta no té cap autenticació. Com que el
backend treballa amb la *service_role key* de Supabase, **salta les polítiques
RLS**: la policy `kals_insert_organizer` (migració `20260725134400`, línies
264-273) no s'arriba a aplicar mai en aquest camí.

Conseqüència pràctica: qualsevol client, sense credencials de cap mena, pot
crear un KAL atribuït a **qualsevol** `profiles.id`. No és una fuita de dades,
és una suplantació d'identitat en el camí d'escriptura principal del producte.

Avui no hi ha res al backend que verifiqui qui fa la crida: `symfony/security-bundle`
no és a les dependències i no existeix `config/packages/security.yaml`.

---

## 2. Goals / Non-Goals

### Goals

- Verificar el JWT que emet Supabase Auth i rebutjar tota petició sense un token
  vàlid, **per defecte**, sense que calgui recordar-ho ruta a ruta.
- Exposar a l'aplicació la usuària autenticada amb el seu **id intern** (el ULID
  de `profiles.id`), que és el que fa servir el domini.
- Treure l'`organizerId` del payload de `POST /kal` i derivar-lo del token.
- Deixar escrita la decisió (firewall vs listener) amb el seu perquè, perquè no
  es reobri sense motiu.

### Non-Goals

- **Autorització**: els voters `is_kal_organizer` / `is_kal_member` que
  repliquen els helpers de RLS. Aquest spec només resol *qui ets*, no *què pots
  fer*. Un cop hi hagi usuària autenticada, els voters són un pas separat.
- **Emissió de tokens**: el login i els magic links els fa el frontend contra
  Supabase directament. El backend només verifica.
- **Refresh tokens**: el frontend els gestiona amb supabase-js.
- **El mapatge d'excepcions de domini a HTTP**: és de
  `kal-http-response-and-errors.md`.

---

## 3. Behavior

### 3.1 El firewall

`symfony/security-bundle` amb l'autenticador `access_token` que Symfony 8 porta
de sèrie. Un sol firewall, stateless (no hi ha sessió: cada petició porta el seu
token):

```yaml
# config/packages/security.yaml
security:
    firewalls:
        api:
            pattern: ^/
            stateless: true
            access_token:
                token_handler: App\Shared\Infrastructure\Symfony\Security\SupabaseTokenHandler

    access_control:
        - { path: ^/$, roles: PUBLIC_ACCESS }   # health check
        - { path: ^/, roles: IS_AUTHENTICATED_FULLY }
```

**L'única ruta pública és `GET /`** (el health check de `HealthController`).
Tota ruta nova neix protegida; obrir-la exigeix afegir una línia visible a
`access_control`.

### 3.2 La petició

El client envia el JWT que li ha donat Supabase Auth:

```
Authorization: Bearer <jwt>
```

### 3.3 Què verifica el `SupabaseTokenHandler`

Per ordre, i **totes** són obligatòries:

1. El token es pot decodificar i té la forma d'un JWT.
2. La **signatura** és vàlida (veure §10: JWKS o secret compartit).
3. **`exp`** no ha passat.
4. **`iss`** és l'esperat per al projecte de Supabase configurat.
5. **`aud`** conté el públic esperat.
6. El `sub` existeix i no és buit.

Si qualsevol falla, la petició es rebutja. **No hi ha camí en què un token que
no passi les sis coses arribi a l'aplicació.**

Superat això, el handler resol la identitat (§4): `sub` → `profiles.external_id`
→ `profiles.id`. El que rep l'aplicació és un `AuthenticatedUser` amb el **ULID
intern**, mai l'uuid de Supabase.

### 3.4 Com hi accedeix l'aplicació

El controller obté la usuària per Security i la passa al command. `POST /kal`
queda així:

- L'`organizerId` **surt del token**, no del body.
- El camp `organizerId` al body passa a estar **prohibit**: si hi és, la petició
  es rebutja amb `400 {"error":"kal_invalid_payload"}`, coherent amb la
  validació de payload que ja fa el controller.
- El `CreateKalCommand` no canvia de forma: segueix rebent un `organizerId`
  string; l'únic que canvia és **d'on surt**.

### 3.5 Respostes d'error

Format `{"error": "<codi>"}`, com la resta de l'API.

| Situació | Estat | Codi |
|---|---|---|
| Falta la capçalera `Authorization` o no és `Bearer` | `401` | `auth_token_missing` |
| Token malformat, signatura invàlida, `iss`/`aud` incorrectes | `401` | `auth_token_invalid` |
| Token ben signat però caducat (`exp`) | `401` | `auth_token_expired` |
| Token vàlid, `sub` sense fila a `profiles` | `401` | `auth_profile_not_found` |
| No es poden obtenir les claus de verificació (§3.6) | `503` | `auth_keys_unavailable` |

**Avís d'implementació:** la resposta per defecte de l'autenticador d'`access_token`
**no** té aquesta forma, i per defecte totes les fallades col·lapsen en una
`BadCredentialsException` — que no permet distingir *caducat* de *invàlid*.
Per obtenir la taula de dalt calen dues peces: que el `SupabaseTokenHandler`
llenci excepcions **diferenciades** per cada cas, i un punt únic
(`AuthenticationEntryPointInterface` o un failure handler) que les tradueixi a
aquest cos JSON. Si es dona per fet que Symfony ja ho fa, sortiran 401 amb un
cos que el frontend no sabrà llegir.

### 3.6 Claus de verificació i caché

Si la signatura és asimètrica (§10), el backend baixa el JWKS i **el cacheja amb
un TTL**. Si en caducar el TTL la descàrrega falla, **se segueix verificant amb
les claus cachejades**: una incidència de xarxa de mig minut no ha de tombar
totes les escriptures. Només si no hi ha **cap** clau disponible (arrencada en
fred amb Supabase caigut) es retorna `503 auth_keys_unavailable`.

Conseqüència acceptada: durant la finestra de caché, una clau revocada a
Supabase encara es considera vàlida aquí. És el preu de no dependre de la xarxa
a cada petició, i el TTL és la palanca per ajustar-ho.

---

## 4. Identity mapping

El nus d'aquest spec, i la raó per la qual no és un canvi de dues línies.

El `sub` del JWT de Supabase és l'**uuid d'`auth.users`**. El domini de KAL App
**no** el fa servir mai: `kals.organizer_id` i la resta de FKs apunten a
`profiles.id`, que és un **ULID de 26 caràcters** generat a la BD pel trigger
`handle_new_user()`. La columna que lliga els dos móns és `profiles.external_id`
(única, migració `20260716190425`).

És exactament el que fan les RLS avui:

```sql
-- migració 20260725134400, línies 201 i 223
and p.external_id = (select auth.uid()::text)
```

Per tant, autenticar implica **una consulta a `profiles`**: `external_id` → `id`.
Això obliga a obrir el context `src/User/`, que el `CLAUDE.md` ja preveu però
que encara no existeix. Es crea **mínim**, només el camí de lectura:

```
src/User/
  Domain/
    UserId.php                    value object (ULID intern)
    ExternalId.php                value object (uuid de Supabase Auth)
    AuthenticatedUser.php         { UserId, ExternalId }
    UserRepositoryInterface.php   findByExternalId(ExternalId): ?AuthenticatedUser
  Infrastructure/
    Persistence/
      DbalUserRepository.php      SELECT id FROM profiles WHERE external_id = ?
```

Sense `Application/` ni `UI/`: no hi ha cap command ni cap endpoint d'usuària en
aquest spec. Registrar-lo a `config/services.yaml` (no cal `routes.yaml`, no hi
ha controllers).

**Regla que això fa complir:** el `Domain/` de User no coneix ni Symfony ni el
JWT. Qui parla amb el token és l'adaptador de `Shared/Infrastructure`; el que
creua la frontera cap a l'aplicació és un `AuthenticatedUser` amb el ULID intern.

---

## 5. Scenarios

### Feliç

1. **Crear un KAL autenticada.** L'organitzadora té sessió al frontend.
   `POST /kal` amb `Authorization: Bearer <jwt vàlid>` i un body **sense**
   `organizerId`. El backend verifica el token, resol `sub` → `profiles.id`, i
   crea el KAL amb aquest ULID com a `organizer_id`. Resposta `201`.
2. **Health check sense token.** `GET /` sense cap capçalera retorna `200`.

### Error

3. **Sense capçalera.** `POST /kal` sense `Authorization` → `401
   auth_token_missing`. **No s'arriba a validar el payload**: primer l'auth.
4. **Capçalera present però no `Bearer`** (p.ex. `Basic`) → `401
   auth_token_missing`.
5. **Token amb la signatura tocada** → `401 auth_token_invalid`.
6. **Token caducat** (`exp` al passat, signatura correcta) → `401
   auth_token_expired`, **no** `auth_token_invalid`.
7. **Token d'un altre projecte de Supabase** (`iss` o `aud` diferents, signatura
   vàlida per al seu emissor) → `401 auth_token_invalid`. És el cas que un
   handler mal escrit deixa passar.
8. **Token vàlid sense perfil.** El `sub` no té fila a `profiles` → `401
   auth_profile_not_found`. Indica que el trigger `handle_new_user()` ha fallat
   o que el perfil s'ha esborrat (RGPD); es vol distingir als logs.
9. **`organizerId` al body.** Token vàlid i body que encara porta el camp →
   `400 kal_invalid_payload`. **No** es crea el KAL ni amb l'un ni amb l'altre.
10. **JWKS inabastable amb caché calenta.** La descàrrega falla però hi ha claus
    cachejades → la petició es resol amb normalitat.
11. **JWKS inabastable en fred.** Cap clau disponible → `503
    auth_keys_unavailable`.
12. **Ruta nova sense tocar `access_control`.** Una ruta afegida després
    d'aquest spec, sense cap canvi de configuració, respon `401` sense token.
    És l'escenari que justifica el firewall (§9) i s'ha de provar.

---

## 6. Acceptance criteria

- [ ] `POST /kal` sense token vàlid retorna `401` i **no** crea cap fila.
- [ ] `POST /kal` amb token vàlid crea el KAL amb `organizer_id` = el ULID de
      `profiles.id` corresponent al `sub`, **no** l'uuid de Supabase.
- [ ] Enviar `organizerId` al body retorna `400 kal_invalid_payload`.
- [ ] Hi ha un test per **cadascun** dels casos 3 a 11, construint els tokens a
      propòsit (caducat, mal signat, `iss`/`aud` d'un altre projecte). Els casos
      negatius són el valor d'aquest spec: un handler que se salti `exp` o `aud`
      passa tots els tests feliços.
- [ ] Existeix un test que afegeix una ruta nova i comprova que respon `401`
      sense tocar `security.yaml` (escenari 12).
- [ ] `GET /` segueix responent `200` sense token.
- [ ] El cos de totes les respostes d'error té la forma `{"error": "<codi>"}`,
      amb els codis de la taula de §3.5 — verificat passant pel kernel HTTP, no
      només per unit test del handler.
- [ ] `App\User\Domain` no importa res de Symfony ni de Doctrine, i els arch
      tests de `tests/Arch/` ho comproven per `App\User\*` (no només per les
      plantilles buides d'`App\Domain`).
- [ ] `make qa` passa: PHPStan `level: max` amb els `@throws` de tota la cadena
      del handler, CS Fixer i Pest.

---

## 7. Constraints

- **Layering**: `src/User/Domain/` i `src/Kal/Domain/` no poden dependre de
  Symfony ni de DBAL. El `TokenHandler` i el firewall són infraestructura i van
  a `src/Shared/Infrastructure/Symfony/Security/`. Els arch tests de
  `tests/Arch/` s'han d'estendre per cobrir `App\User\*` — avui les plantilles
  contra `App\Domain` passen en va.
- **PHPStan `level: max` amb `missingCheckedExceptionInThrows`**: tota excepció
  pròpia documentada amb `@throws`, comprovat recursivament. Compte amb la
  cadena del `TokenHandler`, que en llençarà unes quantes.
- **Errors com a codis**, mai frases per a humans.
- **Deny-by-default**: afegir una ruta nova no ha de requerir recordar-se de
  protegir-la. Deixar-la pública ha de ser un acte explícit i visible a la
  config.
- **Dependència nova**: `symfony/security-bundle`. És la primera d'aquest spec i
  cal justificar-la al PR.
- **Tests d'integració contra Supabase local**, mai producció.

---

## 8. Out of scope

- Voters d'autorització (`is_kal_organizer`, `is_kal_member`).
- Protegir la resta d'endpoints d'escriptura més enllà de `POST /kal` — hi
  quedaran coberts pel firewall igualment, però els seus contractes no es
  revisen aquí.
- El context `src/User/` complet: perfil, avatar, locale, dret a esborrat.
- Tokens de servei per al MCP de llanes (el segon client previst al
  `CLAUDE.md`). El disseny no els ha d'impedir, però no s'implementen.
- Rate limiting i protecció contra força bruta.

---

## 9. Trade-offs & risks

### Firewall de Security vs listener de kernel

S'ha valorat un subscriber a `kernel.request` que validés el token i el deixés
als atributs de la `Request`. Es descarta:

| | Firewall | Listener |
|---|---|---|
| Ruta nova | protegida per defecte | **desprotegida** fins que algú toqui l'`if` |
| Qui és l'usuària | `Security::getUser()` | atribut de la Request, convenció pròpia |
| Autorització | voters, `#[IsGranted]` | tot a mà |
| 401/403 | els dona el component | te'ls fas tu |
| Cost | +1 dependència, +1 fitxer de config | zero dependències |

El motiu decisiu és el primer: el bug que aquest spec arregla és **exactament**
"algú no es va recordar de protegir una ruta". Un mecanisme opt-in reprodueix la
mateixa classe d'error la propera vegada. El cost és una dependència de primera
part i un `security.yaml` de quinze línies.

### Riscos

- **La verificació del JWT és codi de seguretat escrit a mà.** Un
  `TokenHandler` que s'oblidi de comprovar `exp`, l'`iss` o l'`aud` accepta
  tokens que hauria de rebutjar, i els tests feliços no ho detecten. Cal provar
  explícitament els casos negatius amb tokens construïts a propòsit.
- **Dependència de xarxa nova** si es va per JWKS: el backend ha de baixar les
  claus públiques. Sense caché, cada petició en depèn. Veure §10.
- **`src/User/` neix mínim i pot quedar-hi.** El risc real no és el disseny,
  és que "mínim" es converteixi en permanent i el context creixi tort quan
  arribi la tasca d'usuàries de debò.
- **El frontend haurà de canviar** el mateix dia: deixar d'enviar `organizerId`
  i començar a enviar la capçalera. És un canvi coordinat entre dos repos.

---

## 10. Efectes col·laterals detectats

Coses que aquest canvi trenca o necessita, i que no són òbvies llegint només
l'enunciat. Detectades repassant el codi existent:

- **Els tests funcionals actuals passaran a fallar.**
  `tests/Kal/Ui/Http/KalCreateControllerTest.php` fa peticions sense cap
  capçalera d'autenticació; amb el firewall actiu, totes respondran `401` en
  comptes del que asserten. Cal decidir com s'autentiquen els tests (un token
  de prova signat amb claus de test, o un `TokenHandler` substituït al
  container de test via `config/services_test.yaml`, que ja existeix i ja
  s'usa per al repositori en memòria).

- **La configuració del handler necessita variables d'entorn que avui no
  arriben als tests.** Caldrà l'URL del projecte de Supabase, l'`iss` i l'`aud`
  esperats. Però `.env.test` **no es carrega**: `phpunit.xml.dist` arrenca amb
  `vendor/autoload.php`, no hi ha `tests/bootstrap.php` i enlloc es crida
  `Dotenv::bootEnv()`. Avui no es nota perquè cap test instancia serveis que
  llegeixin `%env(...)%`; el `TokenHandler` serà el primer, i fallarà amb un
  `EnvNotFoundException` confús. **Arreglar el bootstrap forma part d'aquesta
  feina.**

- **El `DbalUserRepository` consultarà `profiles`, que en local ve d'un stub.**
  `supabase/migrations/20260716190425_profiles_stub.sql` crea la taula amb
  només `id` i `external_id` — prou per a aquest spec. Dos problemes coneguts
  d'aquell fitxer (comparteix prefix de versió amb la migració real d'Users, i
  no activa RLS) no els resol aquest spec, però qui hi treballi els trobarà.

---

## 11. Decisió sobre la signatura, i el que en queda obert

**Decidit (2026-08-02): es va per JWKS.** El projecte exposa l'endpoint i
retorna JSON, configurat via `SUPABASE_JWKS_URL`. Queda descartat el secret
HS256 compartit, que a més era la pitjor opció de les dues: qui té el secret pot
**forjar** tokens, no només verificar-los.

Conseqüències, ja recollides al spec:

- El `TokenHandler` baixa el JWKS i **només verifica**; la clau privada no surt
  mai de Supabase.
- Cal la caché amb TTL i el comportament degradat de §3.6 (si la refresca falla,
  se segueix amb les claus que hi ha; `503 auth_keys_unavailable` només en fred).
- Rotar claus a Supabase no obliga a desplegar el backend.
- La URL del JWKS és configuració (`SUPABASE_JWKS_URL`), i per tant entra al
  problema del bootstrap d'entorn als tests descrit a §10.

### El que encara s'ha de confirmar

**Quin `alg` i `kty` tenen les claus.** Que l'endpoint retorni JSON és condició
necessària però no suficient: un projecte encara amb el secret heretat també
serveix aquell path, però amb `"keys": []`. Cal veure que l'array **no** sigui
buit i amb quin algorisme, perquè determina la llibreria de verificació i el
codi del handler (ES256 i RS256 no es verifiquen igual).

```bash
curl -s "$SUPABASE_JWKS_URL" | jq '.keys[] | {kty, alg, kid}'
```

Si això surt buit, la decisió de dalt no és vàlida i cal tornar a §11 abans
d'escriure el handler.
