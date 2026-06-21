# Plan 001 — Helpdesk Ticketing Core (TALL) — the HOW

**Spec:** specs/001-ticketing/spec.md
**Date:** 2026-06-21
**Gate:** CLAUDE.md / AGENTS § No-negociables — strict types, final, readonly VO/DTO, backed enums,
UUIDv7, reversible migrations, Spatie Data SSOT, thin Livewire → Actions, domain ⊥ Illuminate\Http,
bilingual, dark/light, PHPStan L10, Pint, Pest (DB tests in Feature).

---

## 0. Conventions confirmed from the scaffold (FOLLOW VERBATIM)

- **Livewire 4 SFCs**, not Volt, not Folio. A page is a `.blade.php` opening with
  `<?php new #[Layout('layouts.app')] #[Title('…')] class extends Livewire\Component { … }; ?>`
  then the markup. Wired by `Route::livewire('/path', 'namespace::name')->name('…')`.
- **Namespaces** (config/livewire.php `component_namespaces`): `pages::*` →
  `resources/views/livewire/pages/`, `tickets::*` → `resources/views/livewire/tickets/`.
  Auth components go under a new `auth::*` namespace → `resources/views/livewire/auth/`
  (register it in config/livewire.php).
- **Layout** is `layouts.app` (already FOUC-guarded, dark-mode, skip-link, `@fluxScripts`).
- **i18n:** `__('file.key')` + `@php($lang = app()->getLocale())`; enum labels via
  `$status->label()[$lang] ?? $status->label()['en']`. Lang files live in `lang/{en,es}/*.php`,
  each `declare(strict_types=1)` + `return [...]`.
- **UUIDv7** via `HasUuids` + `newUniqueId()` returning `Str::uuid7()` (copy from Ticket/User).
- **DB tests** under `tests/Feature` (RefreshDatabase wired in Pest.php for Feature + Unit). Arch in
  `tests/Arch`. Livewire tested via `Livewire::test('namespace::name')`.

---

## 1. Agent identity (decision)

Gate on `auth` only — any authenticated `User` is an agent for this slice. **No role migration.**
The "assignee" picker lists all `User`s. A dedicated `agents`/role concept is deferred (note in
NEXT_STEPS). This keeps the slice minimal and avoids an irreversible schema commitment.

> If implementation finds it genuinely needs to distinguish agents from requesters in the assignee
> picker, STOP and ask before adding a column.

---

## 2. Data model & migrations (reversible)

### 2.1 New migration — `create_ticket_comments_table`
`database/migrations/<ts>_create_ticket_comments_table.php`

```
Schema::create('ticket_comments', function (Blueprint $table): void {
    $table->uuid('id')->primary();                       // UUIDv7
    $table->foreignUuid('ticket_id')->constrained('tickets')->cascadeOnDelete();
    $table->foreignUuid('author_id')->constrained('users')->cascadeOnDelete();
    $table->text('body');
    $table->timestamps();
    $table->index(['ticket_id', 'created_at']);          // chronological listing per ticket
});
```
`down()` → `Schema::dropIfExists('ticket_comments');`

No other migrations. `assignee_id` already exists on `tickets`. No agent-role column (see §1).

---

## 3. Domain layer additions (`app/Domain/Ticketing/`)

### 3.1 Model — `Models/TicketComment.php`
`final`, `HasUuids` + `newUniqueId()`/`uniqueIds()` (copy pattern), `$fillable = ['ticket_id','author_id','body']`,
relations: `ticket(): BelongsTo<Ticket>`, `author(): BelongsTo<User>` (FK `author_id`).
Add `comments(): HasMany<TicketComment>` to **Ticket** (ordered by `created_at` asc at query time).
Add a `CommentFactory` → `database/factories/TicketCommentFactory.php` (fictional body, `ticket_id`/`author_id` via factories).

### 3.2 DTO — `Data/CommentOnTicketData.php`
`final class … extends Spatie\LaravelData\Data`:
```
#[StringType, Min(1), Max(5000)] public string $body
```
SSOT for comment validation.

### 3.3 DTO — `Data/AssignTicketData.php` (optional but preferred for symmetry)
```
public ?string $assigneeId = null   // #[Nullable, Uuid, Exists('users','id')]
```
(Validate the assignee exists; null = unassign.) If simpler, the component MAY validate via
`#[Validate]` mirroring these rules — DTO remains the SSOT of record.

### 3.4 Action — `Actions/CommentOnTicket.php`
`final readonly`, `handle(Ticket $ticket, CommentOnTicketData $data, User $author): TicketComment`.
Trim/guard handled by DTO; create the comment inside the relation. No transaction needed (single insert)
— but wrap in `DB::transaction` only if it grows. Returns the persisted comment.

### 3.5 Action — `Actions/AssignTicket.php`
`final readonly`, `handle(Ticket $ticket, ?User $assignee): Ticket`. Sets `assignee_id` =
`$assignee?->getKey()` and saves. Idempotent; null unassigns. (Resolving the `User` from an id is the
component's job; the Action takes a typed `?User`.)

### 3.6 Reuse unchanged
`CreateTicket` (already resolves requester from the passed `User`), `TransitionTicket` (already fails
loud via `InvalidTicketTransition`), `TicketStatus`/`TicketPriority` enums, `TicketSubject` VO,
`CreateTicketData`. Do NOT modify their behaviour.

---

## 4. Presentation — Livewire 4 SFCs + Flux

All under `resources/views/livewire/`. Each is a thin `class extends Component` delegating to Actions.
Catch `InvalidTicketTransition` and `Illuminate\Validation\ValidationException`/`AuthenticationException`
to surface **inline** errors — never let them 500.

### 4.1 Auth namespace (register `auth::` in config/livewire.php)

**`auth/login.blade.php`** (`auth::login`)
- State: `#[Validate('required|string|email')] public string $email = ''`,
  `#[Validate('required|string')] public string $password = ''`, `public bool $remember = false`.
- `login()`: `Auth::attempt(['email'=>…,'password'=>…], $this->remember)` →
  on success `regenerate session` + `return $this->redirectRoute('tickets.index')`;
  on failure `throw ValidationException::withMessages(['email' => __('auth.failed')])`.
- `mount()`: if `Auth::check()` redirect to `tickets.index` (AUTH-5).
- View: `flux:input` email, `flux:input` type=password, `flux:checkbox` remember, `flux:button` submit
  with `wire:submit="login"`; `@error`/Flux field errors inline. Bilingual labels.

**Logout** — a tiny `auth::logout` is overkill; instead add a `logout()` method on a shared header
component OR a `Route::post('/logout', …)`. **Decision:** a `logout()` action on the **list** page's
header is awkward across pages; use a dedicated full-page-less approach: add `logout()` to a small
SFC `auth::logout` rendered in the layout, OR simplest — `Route::post('/logout')` closure is banned
(no closures). **Final decision:** add a thin `tickets::*`-shared Flux header partial that each page
includes, exposing a `logout` via a tiny SFC `auth::session-menu` (`auth::session-menu`):
`logout()` → `Auth::logout()`, invalidate+regenerate session token, `redirectRoute('login')`.
Render `<livewire:auth::session-menu />` in each ticket page header.

### 4.2 Tickets namespace

**`tickets/index.blade.php`** (`tickets::index`) — the board
- Uses `Livewire\WithPagination`. State: `public string $search=''`,
  `public string $status=''` (enum value or ''), `public string $priority=''` (enum value or '').
- `#[Computed]` or render-time query:
  ```
  Ticket::query()
    ->with(['requester:id,name', 'assignee:id,name'])      // no N+1 (LIST-4)
    ->when($search, fn($q) => $q->where('subject','ilike',"%{$search}%"))
    ->when($status, fn($q) => $q->where('status',$status))
    ->when($priority, fn($q) => $q->where('priority',$priority))
    ->latest()
    ->paginate(15);
  ```
  (Postgres `ilike` for case-insensitive search.)
- `updatingSearch/Status/Priority` → `resetPage()`.
- View: filter row — `flux:input wire:model.live.debounce.300ms="search"`,
  two `flux:select wire:model.live` (status/priority) whose options iterate the enum `cases()` with
  bilingual labels + an "All" option. Table (`flux:table` if available, else a semantic `<table>`):
  columns Subject (link to detail), Requester, Assignee, Priority badge (`color()`/`label()`),
  Status badge, Created (`diffForHumans`). Empty state. Pagination links. "New ticket" → `tickets.create`.
  Header includes `<livewire:auth::session-menu />`.

**`tickets/create.blade.php`** (`tickets::create`) — the create form
- State: `public string $subject=''`, `public string $body=''`,
  `public string $priority = TicketPriority::Medium->value`.
- `save()`:
  ```
  $data = CreateTicketData::validate([...]) // or validateAndCreate → DTO
  $ticket = app(CreateTicket::class)->handle($data, Auth::user());
  return $this->redirectRoute('tickets.show', $ticket);   // CREATE-1
  ```
  `CreateTicketData::validateAndCreate([...])` throws `ValidationException` on bad input → Livewire
  renders inline errors, nothing persisted (CREATE-2). Requester = `Auth::user()` (server-side).
- View: `flux:input` subject, `flux:textarea` body, `flux:select` priority (enum cases, bilingual),
  `flux:button` submit `wire:submit="save"`; inline errors; Cancel → `tickets.index`.

**`tickets/show.blade.php`** (`tickets::show`) — detail + transition + assign + comment
- `mount(Ticket $ticket)` via route-model binding; store `public Ticket $ticket` (or `#[Locked] public string $ticketId` + resolve). **Decision:** bind the model, keep id `#[Locked]`.
- State for comment: `public string $commentBody=''`.
- State for assign: `public ?string $assigneeId=null` (init from `$ticket->assignee_id`).
- `transition(string $target)`:
  ```
  try {
    $status = TicketStatus::from($target);
    app(TransitionTicket::class)->handle($this->ticket, $status);
    $this->ticket->refresh();
  } catch (InvalidTicketTransition|\ValueError $e) {
    $this->addError('transition', __('tickets.transition_illegal'));   // TRANS-2 graceful
  }
  ```
  Buttons rendered **only** for `$ticket->status->allowedTransitions()` (TRANS-3) with bilingual labels.
- `assign()`: resolve `?User` from `$this->assigneeId` (null allowed) → `app(AssignTicket::class)->handle($ticket, $user)` → refresh. Validate id exists (DTO or `#[Validate('nullable|uuid|exists:users,id')]`).
- `addComment()`:
  ```
  $data = CommentOnTicketData::validate(['body'=>$this->commentBody]); // inline errors if blank (COMMENT-2)
  app(CommentOnTicket::class)->handle($this->ticket, CommentOnTicketData::from(['body'=>$this->commentBody]), Auth::user());
  $this->commentBody=''; $this->ticket->load('comments.author');
  ```
- View: header (subject, `#id`, created, status+priority badges), `<livewire:auth::session-menu />`;
  description card; **transitions** row of `flux:button` (one per allowed target);
  **assign** `flux:select` of users (bilingual "Unassigned" option) + `flux:button` assign;
  **comments** list (author name + `diffForHumans` + body, ascending) + `flux:textarea` + post button.
  All errors inline. WCAG: badges pair colour with text.

---

## 5. Routes (`routes/web.php`)

```php
Route::livewire('/login', 'auth::login')->name('login');      // guest-reachable

Route::middleware('auth')->group(function (): void {
    Route::livewire('/tickets', 'tickets::index')->name('tickets.index');
    Route::livewire('/tickets/create', 'tickets::create')->name('tickets.create');
    Route::livewire('/tickets/{ticket}', 'tickets::show')->name('tickets.show');
});
```
- Keep the existing `/` dashboard route; move it inside the `auth` group too (the dashboard becomes
  agent-only). Logout is handled by the `auth::session-menu` SFC's `logout()` (no closure route).
- No closures, every route `->name()` (AGENTS rule 5).
- Configure the auth redirect: unauthenticated → `route('login')` (Laravel 12
  `bootstrap/app.php` `->withMiddleware(fn($m) => $m->redirectGuestsTo('/login'))` or rely on the
  default `login` named route — verify in `bootstrap/app.php`).

---

## 6. Seeder

`database/seeders/DatabaseSeeder.php` — seed ONE demo agent (fictional, no real PII):
`User::factory()->create(['name' => 'Demo Agent', 'email' => 'agent@fluxdesk.test', 'password' => Hash::make('password')])`
plus a handful of `Ticket::factory()` rows across statuses/priorities (requester = the agent or other
factory users) so the board is non-empty in dev. Idempotent-ish (use `firstOrCreate` on the agent email).

---

## 7. i18n keys (bilingual — add to lang/{en,es}/)

- `lang/{en,es}/auth.php` — extend Laravel's: `failed`, plus `email`, `password`, `remember`,
  `sign_in`, `sign_out`, `login_title`, `login_subtitle`.
- `lang/{en,es}/tickets.php` (new): `index_title`, `index_subtitle`, `new`, `subject`, `body`,
  `priority`, `status`, `requester`, `assignee`, `unassigned`, `created`, `search`, `all_statuses`,
  `all_priorities`, `create_title`, `save`, `cancel`, `detail_title`, `description`, `assign`,
  `transition`, `transition_illegal`, `transitioned`, `assigned`, `comments`, `comment_placeholder`,
  `add_comment`, `comment_added`, `empty`, `ticket_number`.
- `lang/{en,es}/a11y.php` — extend with any new aria labels (e.g. `filters`, `ticket_actions`).

---

## 8. Files to create / touch

**Create**
- `database/migrations/<ts>_create_ticket_comments_table.php`
- `app/Domain/Ticketing/Models/TicketComment.php`
- `app/Domain/Ticketing/Data/CommentOnTicketData.php`
- `app/Domain/Ticketing/Data/AssignTicketData.php`
- `app/Domain/Ticketing/Actions/CommentOnTicket.php`
- `app/Domain/Ticketing/Actions/AssignTicket.php`
- `database/factories/TicketCommentFactory.php`
- `resources/views/livewire/auth/login.blade.php`
- `resources/views/livewire/auth/session-menu.blade.php`
- `resources/views/livewire/tickets/index.blade.php`
- `resources/views/livewire/tickets/create.blade.php`
- `resources/views/livewire/tickets/show.blade.php`
- `lang/en/tickets.php`, `lang/es/tickets.php`
- `lang/en/auth.php`, `lang/es/auth.php` (if not present from Laravel default — verify)
- Tests (see spec §4 → §9 below)

**Touch**
- `app/Domain/Ticketing/Models/Ticket.php` — add `comments(): HasMany`.
- `config/livewire.php` — register `auth::` namespace.
- `routes/web.php` — auth group + login route; move dashboard under auth.
- `database/seeders/DatabaseSeeder.php` — demo agent + sample tickets.
- `bootstrap/app.php` — guest redirect to `login` (verify default).
- `lang/en/a11y.php`, `lang/es/a11y.php` — new aria labels.
- `NEXT_STEPS.md` — tick auth + ticket UI; note deferred (SLA, email-to-ticket, portal, passkeys, edit).

---

## 9. Test plan (Pest — Feature for DB/Livewire, Arch for laws)

`tests/Feature/Auth/LoginTest.php`
- AUTH-1 guest → `/tickets` redirects to `/login`.
- AUTH-2 `Livewire::test('auth::login')->set(email,password)->call('login')->assertHasNoErrors()->assertRedirect(route('tickets.index'))` + `assertAuthenticated`.
- AUTH-3 wrong password → `assertHasErrors('email')`, `assertGuest`.
- AUTH-4 `auth::session-menu` `actingAs($agent)->call('logout')->assertRedirect(route('login'))` + `assertGuest`.
- AUTH-5 `actingAs($agent)` on `auth::login` mount → `assertRedirect(route('tickets.index'))`.

`tests/Feature/Tickets/CreateTicketComponentTest.php`
- CREATE-1 valid → `assertHasNoErrors()->assertRedirect(...)`; assert DB has the ticket, status Open, requester = agent.
- CREATE-2 invalid (subject 'no', body 'x') → `assertHasErrors(['subject','body'])`; `assertDatabaseCount('tickets', 0)`.
- CREATE-3 `Event::fake()` → `Event::assertDispatched(TicketOpened::class)`.

`tests/Feature/Tickets/TicketListTest.php`
- LIST-1 filter status → `assertSee(openSubject)->assertDontSee(closedSubject)`.
- LIST-2 filter priority=urgent → see/don't-see.
- LIST-3 search substring → see/don't-see.
- LIST-4 paginate(15): create 20, `assertViewHas` page 1 has 15 / assert pagination present; (optional) `DB::enableQueryLog` N+1 guard.
- gating: guest GET `/tickets` → redirect login.

`tests/Feature/Tickets/TicketShowTest.php`
- TRANS-1 allowed: open ticket → `call('transition','in_progress')->assertHasNoErrors()`; refresh → status InProgress.
- TRANS-2 illegal: open ticket → `call('transition','resolved')->assertHasErrors('transition')`; DB status still Open (no 500).
- TRANS-3 `assertSee` In progress/Pending/Closed labels, `assertDontSee` Resolved label for an Open ticket.
- ASSIGN-1 `set('assigneeId',$agent->id)->call('assign')->assertHasNoErrors()`; DB `assignee_id` = agent.
- ASSIGN-2 set null → assign → `assignee_id` null.
- COMMENT-1 `set('commentBody','Looking into it')->call('addComment')->assertHasNoErrors()`; DB has comment with author_id=agent; `assertSee('Looking into it')`; component `commentBody` reset to ''.
- COMMENT-2 blank body → `assertHasErrors('body'|'commentBody')`; `assertDatabaseCount('ticket_comments',0)`.

`tests/Feature/Tickets/AssignTicketActionTest.php` (+ `CommentOnTicketActionTest.php`)
- Unit-style Action coverage via Feature (DB): assign sets/clears assignee; comment persists with author; `covers(...)`.

`tests/Arch/ArchTest.php` (extend existing)
- `App\Domain\Ticketing\Actions` final (already) — ensure new Actions covered by the existing glob.
- Domain models final + extend Eloquent (extend existing rule to include `TicketComment`).
- New DTOs in `App\Domain\Ticketing\Data` are final.
- `App\Domain` not->toUse `Illuminate\Http\Request` (already) — confirms Actions/DTOs/Models clean.
- (Livewire SFCs are in `resources/views`, not `app/` — not arch-scanned; the law that they stay thin
  is enforced by review, not arch.)

**Run (per LESSONS):** `docker compose exec -T laravel.test ./vendor/bin/pint` then `./vendor/bin/phpstan`;
host `pnpm build`. Do NOT run `pest`/`migrate` against the shared DB unless explicitly cleared — the
test list above is the contract; CI/the implementer runs it in an isolated DB.

---

## 10. Risks / notes

- **Flux table component availability:** if `flux:table` is not in the installed Flux tier, fall back
  to a semantic `<table>` styled with Tailwind tokens (surface/border/text). Spec scenarios don't
  depend on Flux table specifically.
- **`bootstrap/app.php` guest redirect:** verify Laravel 12's default points unauthenticated users to
  the `login` named route; if not, add `redirectGuestsTo`.
- **Comment author vs requester:** comments are agent-authored (`author_id` = `Auth::user()`); the
  requester relationship on the ticket is untouched.
- Deferred items remain explicitly out: SLA, email-to-ticket, customer portal, registration/passkeys,
  ticket edit, attachments, notifications.
