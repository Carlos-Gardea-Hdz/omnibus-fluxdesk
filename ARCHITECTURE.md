# Architecture — FluxDesk

TALL-stack helpdesk built on **DDD-Lite + the Action pattern**. This document
captures the target structure and the load-bearing decisions; pins and rules live
in the OMNIBUS vault (`~/.claude/.agent-rules/`).

## Layered model

```
HTTP / UI (Livewire 4 SFC + Flux)  →  Domain Action  →  Eloquent / DB
        (anemic orchestrator)          (one operation)     (truth)
```

- **Livewire components are anemic orchestrators.** They validate input and call a
  Domain Action; they hold no business logic. Every state-changing wire action is
  treated as untrusted browser input and must authorize server-side.
- **Actions** are `final readonly`, one class = one business operation, with a
  `handle(DTO, ...): Model` signature. Any multi-table write is wrapped in
  `DB::transaction()`.
- **Migrations are the source of truth** for the schema. Models declare `$fillable`,
  cast enums, and eager-load to avoid N+1.

## Directory structure

```
app/
├── Domain/
│   ├── Shared/
│   │   └── ValueObjects/        Uuid (UUIDv7)
│   └── Ticketing/
│       ├── Models/              Ticket (HasUuids, enum casts)
│       ├── Actions/             CreateTicket, TransitionTicket
│       ├── Data/                CreateTicketData (Spatie Data DTO — validation SSOT)
│       ├── Enums/               TicketStatus, TicketPriority (label/color/transitions)
│       ├── ValueObjects/        TicketSubject (validates in constructor)
│       ├── Events/              TicketOpened (carries id only)
│       └── Exceptions/          InvalidTicketTransition
├── Http/Middleware/             SecurityHeaders
└── Models/                      User (UUIDv7)

resources/
├── css/app.css                  Tailwind v4 @theme tokens (OKLCH, 3-tier) + dark mode
├── js/app.js
└── views/
    ├── layouts/app.blade.php    Livewire page layout (FOUC guard, skip link, @flux*)
    └── livewire/
        ├── pages/dashboard.blade.php   full-page SFC (Route::livewire)
        └── tickets/             (reserved for ticket CRUD components)

lang/{en,es}/                    bilingual UI copy (dashboard, a11y, appearance)
tests/
├── Arch/                        executable Law (strict types, final, no debug, DDD boundaries)
├── Unit/                        enums, value objects (no DB)
└── Feature/                     dashboard smoke + create-ticket (PostgreSQL 18)
docs/decisions/                  ADRs
```

## Key decisions

### 1. Livewire 4 SFC (not Volt, not class components by default)
Single-File Components with the raw `<?php new class extends Component {} ?>` head.
Full-page routing via `Route::livewire('/', 'pages::dashboard')`. The `pages` and
`tickets` Livewire namespaces are remapped to `resources/views/livewire/*` so the
component tree mirrors a single root. See ADR-0001.

### 2. UUIDv7 everywhere
`users` and `tickets` use UUIDv7 primary keys (chronologically sortable, opaque in
URLs — defends against IDOR enumeration). Minted via `HasUuids::newUniqueId()`.

### 3. Enum-driven lifecycle
`TicketStatus` owns the only legal transition graph (`canTransitionTo`). The
`TransitionTicket` action fails loud (`InvalidTicketTransition`) on an illegal move.
Enums expose bilingual `label()` and a Flux `color()` — state is never conveyed by
colour alone (WCAG 1.4.1).

### 4. Validation is the DTO
`CreateTicketData` (Spatie Laravel Data) is the single source of truth for input
rules. Value objects (`TicketSubject`) validate in their constructor: if the object
exists, the value is valid.

### 5. Design tokens + dark mode
Tailwind v4 CSS-first config: a 3-tier OKLCH token system where both themes swap
**semantic** token values, so `bg-surface` / `text-text` work in light and dark with
zero per-element `dark:` variants. A synchronous `<head>` script sets `.dark` before
first paint (no FOUC).

### 6. Security headers now, CSP later
`SecurityHeaders` middleware sets the always-safe headers globally; a strict
nonce-based CSP is deferred until the asset graph is final and can roll out in
Report-Only. See ADR-0002.

## Testing strategy

Architecture tests encode the OMNIBUS Law as executable rules (strict types, `final`,
readonly VOs, backed enums, no debug statements, Domain must not depend on
`Illuminate\Http`). Feature tests run against **PostgreSQL 18** (never SQLite) to
catch real driver behaviour. CI gate order: format → static analysis (level 10) →
tests → dependency audits.
