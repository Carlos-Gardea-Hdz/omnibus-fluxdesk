# Plan 002 — Categories + SLA (TALL) — the HOW

**Spec:** specs/002-categories-sla/spec.md
**Date:** 2026-06-21
**Gate:** CLAUDE.md / AGENTS § No-negociables — `declare(strict_types=1)`, `final`, `readonly` VO/DTO,
backed enums (no magic strings), UUIDv7, reversible migrations, Spatie Data SSOT, thin Livewire →
Actions, `App\Domain` ⊥ `Illuminate\Http` and ⊥ `Livewire\Component`, bilingual `__()` + enum
`label()`, dark/light, PHPStan L10, Pint clean, Pest (DB/`__()` tests in Feature, never Unit).

---

## 0. Conventions confirmed from the scaffold (FOLLOW VERBATIM)

- **Livewire 4 SFCs** (not Volt/Folio). A page is a `.blade.php` opening with
  `<?php new #[Layout('layouts.app')] #[Title('…')] class extends Livewire\Component { … }; ?>` then
  markup. Wired by `Route::livewire('/path', 'namespace::name')->name('…')`.
- **Namespaces** registered in `config/livewire.php['component_namespaces']`: `pages`, `tickets`, `auth`.
  Add a new **`categories`** namespace → `resource_path('views/livewire/categories')`.
- **Layout** `layouts.app` (FOUC-guarded, dark mode, `@fluxScripts`).
- **i18n:** `__('file.key')` + `@php($lang = app()->getLocale())`; enum labels via
  `$enum->label()[$lang] ?? $enum->label()['en']`. Lang files `lang/{en,es}/*.php`, each with
  `declare(strict_types=1)` + `return [...]`.
- **UUIDv7:** `HasUuids` + `uniqueIds()` + `newUniqueId(): (string) Str::uuid7()` (copy from `Ticket`).
- **Tests:** DB + Livewire + `__()` under `tests/Feature`; arch in `tests/Arch`. Components tested via
  `Livewire::actingAs($agent)->test('namespace::name')`. `RefreshDatabase` is wired in `Pest.php`.
- **Run gates (in the app container, port 8093):**
  `docker compose exec -T laravel.test ./vendor/bin/pint`,
  `… ./vendor/bin/phpstan`, `… ./vendor/bin/pest`. After editing an SFC blade run
  `… php artisan view:clear`. Host: `pnpm build`.
- **The `transition()` collision is PROHIBITED.** No Livewire component method may be named `transition()`
  (collides with `Livewire\Component::transition()`). 001 already uses `changeStatus()`; reuse it.

---

## 1. Data model & migrations (reversible)

### 1.1 `categories` table — new migration `…_create_categories_table.php`
```php
Schema::create('categories', function (Blueprint $table): void {
    $table->uuid('id')->primary();                 // UUIDv7
    $table->string('name', 60);
    $table->string('color', 20);                   // a CategoryColor enum value (Flux token)
    $table->string('description', 280)->nullable();
    $table->timestamp('archived_at')->nullable();  // soft archive (NOT SoftDeletes)
    $table->timestamps();

    $table->unique('name');                         // CAT-01 DB-level uniqueness
    $table->index('archived_at');                   // active scope
});
```
`down(): Schema::dropIfExists('categories');`

### 1.2 `tickets` alter — new migration `…_add_category_and_sla_to_tickets.php`
```php
Schema::table('tickets', function (Blueprint $table): void {
    $table->foreignUuid('category_id')->nullable()->after('priority')
        ->constrained('categories')->nullOnDelete();
    $table->timestamp('due_at')->nullable()->after('category_id');
    $table->timestamp('resolved_at')->nullable()->after('due_at');

    $table->index('due_at');
    // Supports the live-breach board filter (due_at < now AND resolved_at IS NULL).
    $table->index(['resolved_at', 'due_at']);
});
```
`down()`: inside `Schema::table`, `$table->dropConstrainedForeignId('category_id');` then
`$table->dropIndex(['due_at']); $table->dropIndex(['resolved_at','due_at']);
$table->dropColumn(['due_at','resolved_at']);` — verify reversibility before commit.

> **No data backfill needed**: 001 tickets get `category_id=null`, `due_at=null`, `resolved_at=null`.
> `slaState()` returns `null` ("—") for null `due_at`, so legacy rows render cleanly. (A one-line
> optional backfill of `due_at` from `created_at+slaHours` MAY be added to the migration's `up()` via a
> raw update — OPTIONAL, note it; not required for correctness.)

---

## 2. Enums & value objects

### 2.1 `App\Domain\Ticketing\Enums\CategoryColor: string` (NEW)
Backed enum of the Flux palette allow-list (CAT-02): `Zinc, Red, Orange, Amber, Yellow, Lime, Green,
Emerald, Teal, Cyan, Sky, Blue, Indigo, Violet, Purple, Fuchsia, Pink, Rose` (values = lowercase token).
- `token(): string` → `$this->value` (the Flux `color=` value).
- `label(): array{en,es}` OR omit a bilingual label (colour names are universal) — provide an English
  display label `display(): string` (e.g. "Emerald") for the picker. (Decision: keep it simple — a
  `display()` returning ucfirst(value) is acceptable; no bilingual needed for raw colour names.)
- Place under `Ticketing\Enums` (categories live inside the Ticketing bounded context — do NOT create a
  new domain; this slice enriches Ticketing).

### 2.2 `App\Domain\Ticketing\Enums\SlaState: string` (NEW)
```php
enum SlaState: string {
    case OnTrack = 'on_track';
    case DueSoon = 'due_soon';
    case Overdue = 'overdue';
    public function label(): array {/* en/es: On track/En tiempo · Due soon/Por vencer · Overdue/Vencido */}
    public function color(): string {/* OnTrack emerald · DueSoon amber · Overdue red */}  // SLA-06
}
```

### 2.3 `TicketPriority` — EXTEND (do NOT duplicate)
Add to the existing enum (keep `label`/`color`/`weight`):
```php
public function slaHours(): int {                  // SLA-01 — the ONLY place these numbers live
    return match ($this) {
        self::Urgent => 4,
        self::High => 24,
        self::Medium => 72,
        self::Low => 168,
    };
}
```

### 2.4 `TicketStatus` — EXTEND (do NOT change `isTerminal()`)
Add (SLA-03): `public function stopsSlaClock(): bool { return $this === self::Resolved || $this === self::Closed; }`
Leave `isTerminal()` (Closed-only) untouched.

### 2.5 SLA computation — choose ONE placement (decision: a method on the model + a small VO)
- Add `Ticket::slaState(?CarbonImmutable $now = null): ?SlaState` implementing SLA-04 exactly. It reads
  `$this->due_at`, `$this->resolved_at`, `$this->priority`. `$now ??= CarbonImmutable::now()` (inject for
  tests). Keep the threshold as `Ticket::DUE_SOON_THRESHOLD_HOURS = 8` (or on `SlaState`). No query.
- Also expose `Ticket::isSlaBreachedLive(?CarbonImmutable $now = null): bool` =
  `due_at !== null && resolved_at === null && now >= due_at` (mirrors the SQL filter SLA-05) for symmetry
  in views/tests.
- Rationale: a free `SlaWindow` VO is optional; the logic is small and lives naturally on the aggregate.
  If a VO is preferred for testability, `final readonly class SlaWindow` taking
  `(CarbonImmutable $dueAt, ?CarbonImmutable $resolvedAt, int $soonThreshold)` with `->state(now)` is an
  acceptable alternative — but keep ONE source of truth, do not split the logic.

---

## 3. Model

### 3.1 `App\Domain\Ticketing\Models\Category` (NEW) — adapt the CMS Category pattern
- `final class Category extends Model` with `HasUuids` (UUIDv7 `newUniqueId()` copied from `Ticket`).
- `$fillable = ['name','color','description','archived_at']`.
- casts: `['color' => CategoryColor::class, 'archived_at' => 'immutable_datetime']`.
- `@property` block: `string $id, string $name, CategoryColor $color, ?string $description,
  ?CarbonImmutable $archived_at, CarbonImmutable $created_at, CarbonImmutable $updated_at`.
- Relation `tickets(): HasMany` → `Ticket` on `category_id`.
- `isArchived(): bool => $this->archived_at !== null`.
- Scopes `scopeActive($q) => $q->whereNull('archived_at')` and `scopeArchived($q) => $q->whereNotNull(...)`.
- `protected static function newFactory(): CategoryFactory`.

### 3.2 `Ticket` — EXTEND
- Add `'category_id'` to `$fillable`; add `due_at`,`resolved_at` to casts as `immutable_datetime`.
- Add `@property ?string $category_id`, `?CarbonImmutable $due_at`, `?CarbonImmutable $resolved_at`.
- Add `category(): BelongsTo` → `Category` on `category_id`.
- Add `slaState()` / `isSlaBreachedLive()` / `DUE_SOON_THRESHOLD_HOURS` (§2.5).

---

## 4. DTOs (Spatie Data — validation SSOT)

`App\Domain\Ticketing\Data\` (all `final … extends Data`):

### 4.1 `CategoryData` (create + update share it)
```php
final class CategoryData extends Data {
  public function __construct(
    #[StringType, Min(2), Max(60)] public string $name,
    public CategoryColor $color,                              // enum cast = allow-list enforced
    #[Nullable, StringType, Max(280)] public ?string $description = null,
  ) {}
}
```
- **Uniqueness (CAT-01)** is case-insensitive and must ignore the row being edited. Spatie Data
  `Unique` rule is not expressive enough for the case-insensitive + ignore-self combo, so enforce
  uniqueness in the **Action** (a normalized `whereRaw('lower(name) = ?')` existence check, excluding the
  current id), throwing `DuplicateCategoryName` (a domain `RuntimeException`, message-only, like CMS's
  `CategoryInUseException`) which the component catches → `addError('name', __('categories.name_taken'))`.
  (Belt-and-suspenders: the DB `unique('name')` index is the final guard.)

### 4.2 `CreateTicketData` — EXTEND (do NOT duplicate)
Add an optional category id field:
```php
#[Nullable, Uuid, Exists('categories','id')] public ?string $categoryId = null,
```
Append to the constructor (keep existing `subject/body/priority/assigneeId`).

> NOTE: an `Exists('categories','id')` rule is acceptable here (it does not couple the domain to HTTP).
> It does not enforce "active only" — the **component** only OFFERS active categories in the select, and
> the Action resolves/persists whatever valid id arrives. Selecting an archived id is not reachable from
> the UI; if defense-in-depth is wanted, the Action MAY reject an archived category — note as optional.

---

## 5. Actions (`final readonly`, `App\Domain\Ticketing\Actions\`)

### 5.1 Category CRUD (NEW)
- `CreateCategory::handle(CategoryData $data): Category` — normalize name (trim), uniqueness pre-check
  (case-insensitive), `Category::create([...])`. `DB::transaction` (single table → optional but harmless;
  include for the uniqueness check + insert atomicity).
- `UpdateCategory::handle(Category $category, CategoryData $data): Category` — uniqueness pre-check
  excluding `$category->id`, `$category->update([...])`.
- `ArchiveCategory::handle(Category $category): Category` — `update(['archived_at' => now()])`.
- `RestoreCategory::handle(Category $category): Category` — `update(['archived_at' => null])`.
- (No `DeleteCategory` — hard delete is deferred; archive only.)

### 5.2 `CreateTicket` — EXTEND
Persist `category_id` and stamp `due_at`:
```php
$ticket = Ticket::create([
    // …existing keys…
    'category_id' => $data->categoryId,
    'due_at' => CarbonImmutable::now()->addHours($data->priority->slaHours()),  // SLA-02
]);
```
> Use `CarbonImmutable::now()` captured once so `due_at` is exactly `created_at + slaHours` within
> tolerance. (Because `created_at` is DB-set on insert, AC-SLA-1 asserts `due_at ≈ now()+slaHours` /
> `≈ created_at + slaHours` within 1s.)

### 5.3 `TransitionTicket` — EXTEND (the clock stop, SLA-03)
After the legal-move check + before/with the `update`, compute `resolved_at`:
```php
$resolvedAt = $ticket->resolved_at;
if ($target->stopsSlaClock() && $resolvedAt === null) {
    $resolvedAt = CarbonImmutable::now();
} elseif (! $target->stopsSlaClock()) {
    $resolvedAt = null;                       // re-open clears the stamp
}
$ticket->update(['status' => $target, 'resolved_at' => $resolvedAt]);
```
Keep the existing `InvalidTicketTransition` throw. Single table, no new transaction needed.

> AssignTicket / CommentOnTicket are UNCHANGED.

---

## 6. Livewire components / views (Flux, thin → Actions)

### 6.1 Category management — NEW SFC `resources/views/livewire/categories/index.blade.php`
- `categories::index` page, `#[Layout('layouts.app')]`. THIN: methods delegate to the Category Actions.
- State: `name`, `color` (default a sensible token), `description`, `editingId` (`#[Locked]`-ish — use a
  nullable string; on edit, populate from the row). `showArchived` (`#[Url]`) toggles the archived view.
- Methods: `save()` (create or update via `editingId` → `CategoryData::validateAndCreate([...])` →
  `CreateCategory`/`UpdateCategory`; catch `DuplicateCategoryName` → `addError('name', …)`),
  `edit(string $id)` (load row into form fields / open a `flux:modal`), `archive(string $id)`,
  `restore(string $id)`, `resetForm()`. **NONE named `transition()`.**
- View: a Flux table of categories (name as `{{ }}` escaped, a `flux:badge :color="$c->color->token()"`
  swatch with the name, description, an archived indicator), a create/edit `flux:modal` with
  `flux:input` name, `flux:select` color (options from `CategoryColor::cases()`), `flux:textarea`
  description; inline `@error('name')`. Archive/Restore `flux:button`s per row. `<livewire:auth::session-menu />`.
- Eager-load nothing heavy; `Category::query()->when($showArchived, archived, active)->orderBy('name')->get()`.

### 6.2 `tickets/create.blade.php` — EXTEND
- Add `public ?string $categoryId = null;` to state; pass `'categoryId' => $this->categoryId` into
  `CreateTicketData::validateAndCreate([...])`.
- Add a `flux:select` "Category" with a `value=""` "No category" option + **active** categories
  (`Category::active()->orderBy('name')->get(['id','name'])` exposed via `with()`).

### 6.3 `tickets/index.blade.php` (the board) — EXTEND
- Add `#[Url(history:true)] public string $category = '';` (a category id or '') with
  `updatingCategory(): resetPage()`.
- Add `#[Url(history:true)] public bool $overdueOnly = false;` with `updatingOverdueOnly(): resetPage()`.
- In `tickets()`:
  - `->with([... existing ..., 'category:id,name,color'])` (no N+1 for the badge).
  - `->when($this->category !== '', fn ($q) => $q->where('category_id', $this->category))`
  - `->when($this->overdueOnly, fn ($q) => $q->where('due_at','<',now())->whereNull('resolved_at'))`  // SLA-05
- `with()` adds `'categories' => Category::active()->orderBy('name')->get(['id','name'])`.
- View: a category filter `flux:select` (blank = all, then active categories); an "Overdue only"
  `flux:switch`/checkbox (a11y label via `__()`); a **Category** table column rendering
  `@if($ticket->category)` a `flux:badge :color="$ticket->category->color->token()"` with
  `{{ $ticket->category->name }}` (ESCAPED), else "—"; an **SLA** column rendering
  `@php($sla = $ticket->slaState())` → `@if($sla)` `flux:badge :color="$sla->color()"` with the bilingual
  label, else "—".

### 6.4 `tickets/show.blade.php` — EXTEND
- The `#[Computed] ticket()` query: add `'category:id,name,color'` to the `with([...])`.
- Header badges block: add the category badge (escaped name) when present, and the SLA badge
  (`$ticket->slaState()`), plus the human due date `{{ $ticket->due_at?->diffForHumans() }}` /
  `__('tickets.due_at')`.
- OPTIONAL (depth, allowed by spec): a category re-assign `flux:select` + a method `changeCategory()`
  (NOT `transition()`) delegating to a tiny `Ticket->update(['category_id' => …])` via a new
  `SetTicketCategory` Action — keep it optional; the board+create cover the must-haves. If added, it
  does NOT recompute `due_at` (priority unchanged).

---

## 7. Routes (`routes/web.php`, behind `auth`)
Inside the existing `Route::middleware('auth')->group(...)`:
```php
Route::livewire('/categories', 'categories::index')->name('categories.index');
```
(Single management page covers list/create/edit/archive/restore via the modal + row actions — no extra
routes needed.) Register the `categories` namespace in `config/livewire.php`.

---

## 8. lang keys (bilingual, `lang/{en,es}/`)

### 8.1 NEW `lang/{en,es}/categories.php`
`title, subtitle, new, name, color, description, save, cancel, edit, archive, restore, archived,
active, archived_view, show_archived, empty, name_taken, no_category, manage` (+ ES mirror).

### 8.2 `lang/{en,es}/tickets.php` — ADD keys
`category, all_categories, no_category, sla, sla_state, due_at, overdue_only`.
SLA enum + Category color labels come from the enums' `label()/display()`, not lang files.

### 8.3 Nav
Add a "Categories" link in the board header / nav (`route('categories.index')`, `wire:navigate`) so the
management page is reachable (no dead-end — ux-flow).

---

## 9. Factories & seeds (fictional, NO PII)

### 9.1 `database/factories/CategoryFactory.php` (NEW)
`name => fake()->unique()->words(2, true)` (ucfirst), `color => fake()->randomElement(CategoryColor::cases())`,
`description => fake()->optional()->sentence()`, `archived_at => null`.
States: `archived()` → `['archived_at' => now()]`.

### 9.2 `TicketFactory` — EXTEND
- `category_id => null` default (keep null so existing tests are unaffected) + a `forCategory(Category $c)`
  state and an `overdue()` state (`created_at` in the past + `due_at` past + `resolved_at` null), a
  `resolvedAt(CarbonImmutable $t)` state. Add a `withSla()` state computing `due_at` from the priority if
  helpful. Keep defaults backward-compatible (001 tests must still pass).

### 9.3 `DatabaseSeeder` — EXTEND
Seed ~5 fictional categories (`Category::factory()->count(5)->create()` or an explicit list of demo names
like "Billing / Hardware / Network / Access / Other"), then recycle them into the ticket factory so the
demo board shows categories + a spread of SLA states.

---

## 10. Tests (Pest; DB/`__()`/Livewire in **Feature**, enum-pure logic MAY be Unit)

### Feature — `tests/Feature/Categories/`
- `CategoryCrudTest.php`: create persists (AC-CAT-1); blank name → inline error, no row (AC-CAT-2a);
  duplicate name case-insensitive → inline error, no dup (AC-CAT-2b); edit changes color (AC-CAT-3);
  archive sets `archived_at` + hides from selectors / restore clears (AC-CAT-4); **XSS**: a
  `<script>`-named category renders escaped on the management list AND board (`assertDontSee('<script>',
  false)` / `assertSee('&lt;script&gt;')`) (AC-CAT-5); guest redirected from `/categories` (AC-GATE-1).
- `CategoryOnTicketTest.php`: create-with-category sets `category_id` + show renders badge (AC-TC-1);
  "No category" → null, neutral render (AC-TC-2); board category filter narrows + resets page (AC-TC-3);
  archived category absent from create select + board filter (AC-TC-4).

### Feature — `tests/Feature/Sla/`
- `SlaComputationTest.php`: fresh Urgent ticket `due_at ≈ created_at + 4h` (AC-SLA-1, 1s tolerance);
  fresh Medium `slaState()==on_track` (AC-SLA-2); High created 48h ago, unresolved → `overdue` + board
  badge + appears under overdue filter (AC-SLA-3); Medium due in 4h → `due_soon` (AC-SLA-4); Urgent
  resolved before due → `on_track`/met, NOT overdue, NOT in overdue filter (AC-SLA-5); past-due then
  resolved → `overdue` state BUT NOT in the live overdue filter (AC-SLA-6); transition into Resolved sets
  `resolved_at`, re-open clears it (AC-SLA-7).
- `OverdueFilterTest.php`: the board `overdueOnly` filter returns only live-breach rows and does so via
  SQL — assert the result set + a bounded query count (no per-row PHP filtering) (AC-SLA-8); pagination
  still caps at 15 with the filter on.
- `TransitionTicketSlaTest.php` (Action-level): `TransitionTicket` stamps/clears `resolved_at` per
  `stopsSlaClock()` and still rejects illegal moves.

### Unit — `tests/Unit/`
- `TicketPrioritySlaTest.php`: `slaHours()` mapping (4/24/72/168) and ordering.
- `SlaStateTest.php`: `label()` has both locales (pure array — OK in Unit) + `color()` mapping.
- `CategoryColorTest.php`: every case's `token()` is a valid Flux palette string; `cases()` non-empty.
  (DB-touching / `__()` assertions stay in Feature.)

### Arch — append to `tests/Arch/ArchTest.php`
- The existing `domain models are final … extends Eloquent` expectation already covers
  `App\Domain\Ticketing\Models` (Category lands there → automatically asserted final + Eloquent).
- The existing Actions-final, DTOs-final-extend-Data, enums-backed, domain⊥Http, domain⊥Livewire
  expectations already cover the new classes (same namespaces). Confirm Category lands in
  `App\Domain\Ticketing\Models` so it is in scope; otherwise add an explicit expectation.

### Run
`docker compose exec -T laravel.test php artisan view:clear` (after blade edits), then
`… ./vendor/bin/pint`, `… ./vendor/bin/phpstan`, `… ./vendor/bin/pest`; host `pnpm build`.

---

## 11. Decisions log (resolve the spec's open choices)
- **Category nullable** on tickets (CAT-05) — chosen for trivial 001 backfill + real-world uncategorized
  inbox. Not required.
- **Archive, not hard-delete** (CAT-04) — preserves ticket history; avoids the orphan/restrict-FK
  problem entirely this slice.
- **SLA is wall-clock, priority-driven** — business-hours + per-category override are explicit deferred
  seams (`slaHours()` is the single tuning point; a future `categories.sla_hours` would override).
- **`stopsSlaClock()` added; `isTerminal()` untouched** — Resolved+Closed stop the clock; do not
  repurpose `isTerminal()`.
- **Category lives in the `Ticketing` bounded context** (no new domain) — it is ticket taxonomy.
- **SLA state computed on read** (no jobs/notifications) — escalation delivery deferred.

---

## 12. File manifest (touch list)
**New:** `database/migrations/…_create_categories_table.php`,
`…_add_category_and_sla_to_tickets.php`;
`app/Domain/Ticketing/Models/Category.php`;
`app/Domain/Ticketing/Enums/{CategoryColor,SlaState}.php`;
`app/Domain/Ticketing/Data/CategoryData.php`;
`app/Domain/Ticketing/Actions/{CreateCategory,UpdateCategory,ArchiveCategory,RestoreCategory}.php`;
`app/Domain/Ticketing/Exceptions/DuplicateCategoryName.php`;
(optional `SetTicketCategory.php`);
`resources/views/livewire/categories/index.blade.php`;
`database/factories/CategoryFactory.php`;
`lang/{en,es}/categories.php`;
`tests/Feature/Categories/*`, `tests/Feature/Sla/*`, `tests/Unit/{TicketPrioritySlaTest,SlaStateTest,CategoryColorTest}.php`.
**Edit:** `app/Domain/Ticketing/Enums/{TicketPriority,TicketStatus}.php`;
`app/Domain/Ticketing/Models/Ticket.php`;
`app/Domain/Ticketing/Data/CreateTicketData.php`;
`app/Domain/Ticketing/Actions/{CreateTicket,TransitionTicket}.php`;
`resources/views/livewire/tickets/{create,index,show}.blade.php`;
`routes/web.php`; `config/livewire.php`; `database/factories/TicketFactory.php`;
`database/seeders/DatabaseSeeder.php`; `lang/{en,es}/tickets.php`; `tests/Arch/ArchTest.php`
(only if Category is placed outside `App\Domain\Ticketing\Models`).
