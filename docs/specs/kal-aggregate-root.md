# Kal — aggregate root de domini (Domain-only slice)

> **Estat:** ✅ Domain slice tancat. El codi implementat (`src/Kal/Domain/`,
> `tests/Kal/Domain/`) cobreix Kal + Clue/Clues + File/Files + Locales +
> excepcions i invariants de creació.
>
> **Pròxims passos:**
> - [`kal-aggregate-mvp.md`](kal-aggregate-mvp.md) — repositori, command,
>   migració, camps d'MVP (inviteToken, Meeting, PatternInfo).
> - [`kal-debate.md`](kal-debate.md) — xat/aula (Debate).

## Problema

El projecte KAL App necessitava el primer agregat de domini real — la classe
`Kal` (arrel) i les entitats/value objects que en depenen — com a base sobre
la qual construir Application/Infrastructure/Ui. Sense un model de domini
validat amb tests, cap capa superior pot avançar amb garanties.

## Objectius / No-objectius

### Objectius
- Definir `Kal` com a `AggregateRoot` (`App\Kal\Domain\Kal`).
- Definir `Clue` com a entitat filla amb la seva col·lecció `Clues`.
- Definir `File` (value object) i `Files` (col·lecció) per a fitxers del KAL
  i de cada pista.
- Definir `Locales` (col·lecció de `Locale`) com a idiomes habilitats, amb la
  invariant que tot fitxer ha d'usar un locale habilitat.
- Definir `FileSize`, `FileExtension`, `FileUploadStatus` com a VOs/enums de
  suport.
- Cobertura d'invariants amb tests unitaris (Pest).

### No-objectius
- No implementar `PatternInfo`, `Participation`, `ClueProgress`, `Photo`,
  `Meeting`, `VideoLink`, `inviteToken` ni `debate_rooms`.
- No implementar mutació de camps (rename, canvi de dates, moderació, soft
  delete) — només creació.
- No implementar el repositori (ni port ni adaptador DBAL), ni cap
  command/handler, ni controller.
- No crear cap migració SQL/Supabase.

## Model de domini

Namespace: `App\Kal\Domain`. `Kal` extends `App\Shared\Domain\Model\AggregateRoot`.
Value objects reutilitzats de `Shared\Domain\ValueObject`: `UlidValue`,
`NonEmptyStringValue`, `DateTime`, `Locale`.

---

### `Kal` (aggregate root)

| Camp | Tipus | Obligatori a `create()` |
|---|---|---|
| `id` | `UlidValue` | generat internament (`UlidValue::generate()`) |
| `organizerId` | `UlidValue` | sí |
| `name` | `NonEmptyStringValue` | sí |
| `description` | `?NonEmptyStringValue` | no (per defecte `null`) |
| `files` | `Files` | sí (pot ser `Files::create()` buit) |
| `clues` | `Clues` | sí (pot ser `Clues::create()` buit) |
| `locales` | `Locales` | sí (≥ 1 locale obligatori) |
| `startsOn` | `DateTime` | sí |
| `endsOn` | `?DateTime` | no (per defecte `null`) |
| `coverPath` | `?string` | no — path de Storage, no VO |
| `createdAt` | `DateTime` | generat internament (`DateTime::now()`) |
| `updatedAt` | `DateTime` | generat internament, igual a `createdAt` a la creació |

```php
final class Kal extends AggregateRoot
{
    /** @throws KalException */
    /** @throws InvalidArgumentException */
    public static function create(
        UlidValue $organizerId,
        NonEmptyStringValue $name,
        DateTime $startsOn,
        Locales $locales,
        Files $files,
        Clues $clues,
        ?NonEmptyStringValue $description = null,
        ?DateTime $endsOn = null,
        ?string $coverPath = null,
    ): self;

    /** @throws KalException */
    public function addClue(Clue $clue): void;

    // Explicit getters: id(), organizerId(), name(), description(),
    // startsOn(), endsOn(), coverPath(), createdAt(), updatedAt(),
    // clues(), locales().
    // `files` is accessible via the public property ($kal->files),
    // no explicit getter method.
}
```

**Invariants de `Kal`:**
1. Si `endsOn` no és `null`, ha de ser estrictament posterior a `startsOn` —
   si no, `KalException::invalidDateRange()`.
2. Cada `Clue` dins de `$clues` ha de caure dins el rang del Kal (mateixa
   validació que `addClue()`). Una clue invàlida fa fallar `create()` sencer.
3. Cada `File` dins de `$files` ha d'usar un `Locale` present a `$locales` —
   si no, `KalException::fileLocaleNotEnabled()`.
4. Cada `Clue` dins de `$clues` ha de tenir el seu `file` amb un locale present
   a `$locales` — si no, `KalException::fileLocaleNotEnabled()`.
5. `addClue()` valida tant la invariant de rang (punt 2) com la de locale del
   fitxer (punt 4). No modifica `updatedAt` en aquesta versió.

---

### `Clue` (entitat filla)

| Camp | Tipus | Obligatori a `create()` |
|---|---|---|
| `id` | `UlidValue` | generat internament (`UlidValue::generate()`) |
| `name` | `NonEmptyStringValue` | sí |
| `description` | `?NonEmptyStringValue` | no (per defecte `null`) |
| `file` | `File` | sí |
| `startsOn` | `DateTime` | sí |
| `endsOn` | `DateTime` | sí (obligatori, a diferència de `Kal.endsOn`) |
| `updatedAt` | `DateTime` | generat internament (`DateTime::now()`) |

```php
final class Clue
{
    /** @throws KalException */
    /** @throws InvalidArgumentException */
    public static function create(
        NonEmptyStringValue $name,
        DateTime $startsOn,
        DateTime $endsOn,
        File $file,
        ?NonEmptyStringValue $description = null,
    ): self;

    // Getters: id(), name(), description(), file(), startsOn(), endsOn(),
    // updatedAt().
}
```

**Invariants de `Clue`:**
1. `endsOn` ha de ser estrictament posterior a `startsOn` — si no,
   `KalException::invalidDateRange()`.
2. En afegir-se a un Kal, `startsOn >= Kal.startsOn` (inclusiu) i, si
   `Kal.endsOn` no és `null`, `endsOn <= Kal.endsOn` (inclusiu) — si no,
   `KalException::clueOutsideKalRange()`. Aquesta validació viu a `Kal`, no a
   `Clue::create()`.

---

### `Clues` (col·lecció mutable)

```php
final class Clues
{
    public static function create(Clue ...$clues): self;
    /** @return Clue[] */
    public function all(): array;
    public function add(Clue $clue): void;
}
```

Col·lecció interna amb variadic constructor. `add()` no valida — la validació
la fa `Kal::addClue()` abans d'invocar `Clues::add()`.

---

### `File` (value object — immutable)

| Camp | Tipus |
|---|---|
| `fileName` | `NonEmptyStringValue` |
| `filePath` | `NonEmptyStringValue` |
| `fileSize` | `FileSize` |
| `fileExtension` | `FileExtension` |
| `locale` | `Locale` |
| `uploadId` | `UlidValue` |
| `uploadedAt` | `DateTime` |

`File` és `final readonly class` amb constructor públic (no factory). No té
invariants pròpies; la validació de mida i extensió recau en `FileSize` i
`FileExtension`.

---

### `Files` (col·lecció immutable)

```php
final readonly class Files
{
    public static function create(File ...$files): self;
    /** @return File[] */
    public function all(): array;
}
```

---

### `Locales` (col·lecció immutable)

```php
final readonly class Locales
{
    /** @throws KalException */
    public static function create(Locale ...$locales): self;
    public function contains(Locale $locale): bool;
    /** @return Locale[] */
    public function all(): array;
}
```

**Invariant:** no pot estar buida — si es crea amb zero locales,
`KalException::noLocalesEnabled()`. `contains()` compara per valor normalitzat
(case-insensitive gràcies a la normalització dins `Locale`).

---

### `FileSize` (value object)

```php
final readonly class FileSize
{
    private const int SIZE_MAX = 5 * 1024 * 1024; // 5 MB
    /** @throws InvalidArgumentException */
    public static function create(int $value): self;
    public function value(): int;
    public function equals(self $other): bool;
}
```

Invariant: valor no pot superar `SIZE_MAX` —
`InvalidArgumentException::fileSizeExceeded()`.

---

### `FileExtension` (enum)

```php
enum FileExtension: string
{
    case PDF = 'pdf';
    case CSV = 'csv';
    case TXT = 'txt';

    /** @throws KalFileException */
    public static function tryFromStatus(string $extension): self;
}
```

---

### `FileUploadStatus` (enum)

```php
enum FileUploadStatus: string
{
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    /** @throws KalFileException */
    public static function tryFromStatus(string $status): self;
}
```

No s'usa dins l'agregat a la creació, però viu al domini perquè modela un
concepte del bounded context (estat de la pujada d'un fitxer).

---

### Excepcions

#### `KalException` (extends `DomainException`)

Codis (missatge = codi, sense frase humana):
| Factory method | Codi |
|---|---|
| `invalidDateRange()` | `kal_invalid_date_range` |
| `clueOutsideKalRange()` | `kal_clue_outside_range` |
| `noLocalesEnabled()` | `kal_no_locales_enabled` |
| `fileLocaleNotEnabled()` | `kal_file_locale_not_enabled` |

#### `KalFileException` (extends `DomainException`)

Excepcions de suport per a `FileExtension` i `FileUploadStatus` (missatges
descriptius en anglès, no codis — discrepància documentada a Riscos).

---

## Escenaris

### Happy path
- `Kal::create()` amb només camps obligatoris (`organizerId`, `name`,
  `startsOn`, `locales`, `files` buit, `clues` buit) → Kal vàlid,
  `id`/`createdAt`/`updatedAt` autogenerats, optionals a `null`.
- `Kal::create()` amb tots els camps opcionals (`description`, `endsOn`,
  `coverPath`) → Kal amb tots els valors assignats.
- `Kal::create()` amb `clues` no buit, totes dins de rang i amb locale
  habilitat → Kal creat amb les clues incloses.
- `Kal::addClue()` amb clue vàlida (dins rang, locale habilitat) → s'afegeix.
- `Clue::create()` amb `endsOn` posterior a `startsOn` → Clue vàlid portant
  el `File` que se li ha donat.
- `Locales::create()` amb ≥1 locale → col·lecció vàlida; `contains()` funciona
  case-insensitive.

### Edge / Error paths
- `Kal::create()` amb `endsOn` anterior o igual a `startsOn` →
  `KalException::invalidDateRange()`.
- `Clue::create()` amb `endsOn` anterior o igual a `startsOn` →
  `KalException::invalidDateRange()`.
- `Kal::create()` amb una clue fora de rang → falla tot (`clueOutsideKalRange`),
  cap Kal parcial.
- `Kal::addClue()` amb `Clue.startsOn < Kal.startsOn` →
  `KalException::clueOutsideKalRange()`.
- `Kal::addClue()` amb `Clue.endsOn > Kal.endsOn` (quan `endsOn` no `null`) →
  `KalException::clueOutsideKalRange()`.
- `Kal::addClue()` quan `Kal.endsOn` és `null` → només valida límit inferior.
- Frontera: `Clue.startsOn == Kal.startsOn` → vàlid (inclusiu).
- Frontera: `Clue.endsOn == Kal.endsOn` → vàlid (inclusiu).
- `Kal::create()` amb un `File` dins `files` que usa locale no habilitat →
  `KalException::fileLocaleNotEnabled()`.
- `Kal::create()` amb una clue que porta un `File` amb locale no habilitat →
  `KalException::fileLocaleNotEnabled()`.
- `Kal::addClue()` amb clue que porta `File` amb locale no habilitat →
  `KalException::fileLocaleNotEnabled()`.
- `Locales::create()` sense cap locale → `KalException::noLocalesEnabled()`.
- `FileSize::create()` amb valor > 5 MB →
  `InvalidArgumentException::fileSizeExceeded()`.

## Criteris d'acceptació

- [ ] `Kal::create()` genera `id`, `createdAt`, `updatedAt` automàticament.
- [ ] `Kal::create()` rebutja `endsOn <= startsOn` amb `kal_invalid_date_range`.
- [ ] `Kal::create()` rebutja qualsevol clue fora de rang amb
      `kal_clue_outside_range` — no es crea cap Kal parcial.
- [ ] `Kal::create()` rebutja qualsevol `File` (directe o dins d'una clue) amb
      locale no present a `locales` amb `kal_file_locale_not_enabled`.
- [ ] `Kal::addClue()` valida rang + locale del fitxer.
- [ ] `Clue::create()` rebutja `endsOn <= startsOn` amb `kal_invalid_date_range`.
- [ ] Cada `Clue` porta exactament un `File`.
- [ ] `Locales` no pot ser buida (`kal_no_locales_enabled`).
- [ ] `Locales::contains()` és case-insensitive.
- [ ] `FileSize` rebutja valors > 5 MB.
- [ ] Cap classe del domini depèn de Symfony, DBAL ni Infrastructure.
- [ ] Totes les classes tenen `declare(strict_types=1)` i són `final`.
- [ ] Tests unitaris (Pest) cobreixen tots els happy path i error paths llistats.

## Restriccions

- PHP pur al domini — cap dependència de framework ni infraestructura.
- Invariants es validen a l'arrel (`Kal`), no a les entitats/VOs filles (excepte
  la pròpia invariant `startsOn < endsOn` de `Clue` i la no-buida de `Locales`).
- Errors com a codis (`kal_*`), traducció al frontend (excepció:
  `KalFileException` encara usa missatges humans — veure Riscos).
- `File` és value object (immutable, sense identitat pròpia dins l'agregat).
- `Clues` és mutable (permet `add()`); `Files` i `Locales` són immutables.

## Fora d'abast

Tot el que no és domini pur de creació:
- `Meeting`, `inviteToken`, `pinnedMessage`, `PatternInfo`, `Participation`,
  `ClueProgress`, `Photo`, `VideoLink`, `debate_rooms`.
- Mutació de camps, soft delete, moderació.
- Repositori (port o adaptador), commands/handlers, controllers.
- Migracions SQL/Supabase.
- Events de domini (no se'n registra cap a `create()` en aquesta versió).

Veure [`kal-aggregate-mvp.md`](kal-aggregate-mvp.md) per al camí complet.

## Riscos i preguntes obertes

1. **`KalFileException` usa missatges humans, no codis.** Discrepància amb la
   convenció de `KalException` (codis `kal_*`). A resoldre quan es
   consolidi la gestió d'errors del context — no bloqueja aquesta slice.
2. **`addClue()` no modifica `updatedAt`.** Decisió conscient per a la versió
   Domain-only; quan hi hagi persistència, caldrà decidir si `updatedAt` es
   gestiona al domini o a la infraestructura.
3. **`FileUploadStatus` no s'usa a la creació.** Existeix al domini per modelar
   el cicle de vida complet del fitxer (pujada progressiva); quedarà integrat
   quan es construeixi la infraestructura de Storage.
4. **`Locale` viu a `Shared`.** Decisió correcta (reutilitzable entre contexts),
   però implica que canvis a la llista ISO afecten tots els bounded contexts.
