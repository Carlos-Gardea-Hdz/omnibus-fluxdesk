# Spec 002 — Helpdesk Depth: Categories + SLA (TALL)

**Status:** Draft
**Slice:** 002-categories-sla
**Date:** 2026-06-21
**Branch:** feature/tall-foundation
**Stack:** Laravel 12 · Livewire 4 · Flux 2 · Tailwind v4 · Spatie Data 4 · PostgreSQL 18 · Valkey · Pest
**Builds on:** 001-ticketing (Ticket, TicketStatus/TicketPriority enums, the index/create/show SFCs, the auth gate)

---

## 1. WHAT & WHY

Slice 001 delivered the helpdesk MVP: log in, board, create, transition, assign, comment.
Biblia §4.6 describes a helpdesk with **categories/queues**, **SLA / response targets**,
priorities and **escalation**. This slice adds **depth** to the existing ticket domain — it does
NOT spin up a parallel UI:

1. **Ticket categories** — a small taxonomy an agent manages (CRUD: list / create / edit / archive).
   A ticket belongs to a category (**nullable** — see DOMAIN RULES). The existing create form, show
   page, and board gain a category selector, badge, and filter.
2. **SLA targets** — a priority-driven response/resolution **due time**. Each `TicketPriority` maps to
   an SLA duration (wall-clock hours). A ticket's `due_at = created_at + slaHours`. The board and show
   page surface an **SLA state** — `on_track` / `due_soon` / `overdue` — as a badge, with an
   "overdue only" board filter. Reaching a terminal status **stops the clock** (`resolved_at`);
   an SLA is **met** if `resolved_at <= due_at`.

**Why now:** categories make the board triageable at scale; SLA makes "is this late?" answerable at a
glance — the two features clients actually evaluate a helpdesk on. Both are read-time computations
(no jobs), so the slice stays small and reversible.

**Primary actor:** a support **agent** (an authenticated `User`, exactly as slice 001). No new role.

---

## 2. SCOPE

### In scope
1. **Category management (CRUD):**
   - A `Category` model: `name` (unique), `color` (a Flux palette token), `description` (nullable),
     `archived_at` (nullable — soft archive, NOT Laravel SoftDeletes).
   - A Livewire/Flux management page: list (active + archived), create, edit (inline modal or rows),
     archive / restore. Validated via Spatie Data DTOs. Delegates to Category Actions.
   - Seed ~5 fictional demo categories.
2. **Category on a ticket:**
   - `tickets.category_id` nullable FK → `categories.id`, `nullOnDelete` is N/A (we never hard-delete;
     archiving keeps the row) — use `nullOnDelete` defensively anyway.
   - The **create** form gains a category `<select>` (optional; "No category" default; only **active**
     categories selectable).
   - The **show** page renders the category badge.
   - The **board** gains a category filter `<select>` and a category badge column/cell.
   - `CreateTicket` is **extended** (not duplicated) to persist `category_id` + compute `due_at`.
3. **SLA (priority-driven, wall-clock):**
   - `TicketPriority::slaHours(): int` — the target window per priority (Urgent 4h, High 24h,
     Medium 72h, Low 168h — see DOMAIN RULES; tune only via the enum).
   - `tickets.due_at` (timestamptz, nullable) persisted at creation = `created_at + slaHours`.
   - `tickets.resolved_at` (timestamptz, nullable) stamped when the ticket reaches a **terminal**
     status (Resolved or Closed) and cleared if it leaves terminal (re-open).
   - A `SlaState` backed enum `{on_track, due_soon, overdue}` with `label()` (bilingual) + `color()`.
   - A read-time computation `Ticket::slaState(?CarbonImmutable $now = null): ?SlaState`:
     - `null` when there is no `due_at` (legacy/edge rows) — render a neutral "—".
     - **Resolved before due** → never `overdue`: if `resolved_at !== null` then state is decided
       against `resolved_at` (met = `on_track`), the clock is stopped.
     - Otherwise compared against `now`: `overdue` if past `due_at`; `due_soon` if within the
       "soon" window (DUE_SOON_THRESHOLD_HOURS before `due_at`); else `on_track`.
   - The **board** gains an "Show overdue only" filter (a Flux switch/checkbox) that narrows to tickets
     whose `due_at < now` AND `resolved_at IS NULL` (an unmet, breached SLA) — computed in SQL, not PHP.
   - The **show** page renders the SLA badge + the human `due_at`.

### Out of scope — DEFERRED (note only, do not build)
- **Automation / rules engine** (auto-categorize, auto-assign, auto-escalate by condition).
- **Business-hours / calendar SLA** (working-hours, holidays, pause-on-pending). SLA here is **plain
  wall-clock** hours from `created_at`. Note the seam (`slaHours()` is the single tuning point).
- **Escalation delivery** (email/Slack/push on breach, scheduled `due_soon` reminders, a queued
  escalation job). State is computed **on read**; no scheduler, no notifications.
- **Per-category SLA overrides** (SLA is priority-driven only this slice; a `categories.sla_hours`
  override column is a future seam — do NOT add it now).
- **Customer/requester portal**, category merge/reorder, category icons, nested categories.
- **Ticket edit of subject/body** (unchanged from 001 — still out of scope). The **show** page MAY
  add category re-assignment + recompute (see DOMAIN RULES) since that is category depth, but a full
  ticket-edit form is still deferred.

---

## 3. DOMAIN RULES (authoritative)

### Categories
- **CAT-01** `name` is required, 2–60 chars, **unique** (case-insensitive at the app layer via a
  normalized check + a DB unique index on `name`). Trim before persist.
- **CAT-02** `color` is one of a fixed allow-list of Flux palette tokens
  (`zinc, red, orange, amber, yellow, lime, green, emerald, teal, cyan, sky, blue, indigo, violet,
  purple, fuchsia, pink, rose`) — a backed enum or a `Rule::in` against a constant list. Never a
  free-form string (it is interpolated into a Flux `color=` prop).
- **CAT-03** `description` optional, ≤ 280 chars.
- **CAT-04** **Archive, not delete.** Archiving sets `archived_at = now()`; the row survives so tickets
  keep their reference + label. An **archived** category is hidden from the create/board selectors but
  still renders (greyed/"archived") on existing tickets and in the management "archived" view. Restore
  clears `archived_at`. There is **no hard delete** in this slice (avoids orphaning ticket history) —
  if a future hard-delete is added it MUST guard with an in-use pre-check.
- **CAT-05** A ticket's category is **nullable** ("No category" / unassigned triage). Deciding nullable
  (not required) keeps backfill of 001 tickets trivial and matches real helpdesks where uncategorized
  is a valid inbox state.

### SLA
- **SLA-01** SLA window per priority (wall-clock hours), the **only** place these numbers live is
  `TicketPriority::slaHours()`:
  - Urgent → **4** h
  - High → **24** h
  - Medium → **72** h
  - Low → **168** h (7 days)
- **SLA-02** `due_at` is stamped **once at creation** = `created_at + slaHours(priority)`. It does NOT
  recompute when priority later changes (priority mutation is not in scope this slice; note the seam).
- **SLA-03** `resolved_at` is the clock-stop. Set to `now()` when `TransitionTicket` moves a ticket
  **into** a terminal status (`Resolved` or `Closed`) and `resolved_at` is currently null. Cleared
  (set null) when a ticket transitions **out of** terminal back to a live status (re-open). Terminal =
  `TicketStatus::Resolved` OR `TicketStatus::Closed`.
  > NOTE: the existing `TicketStatus::isTerminal()` returns true only for `Closed`. SLA needs
  > Resolved+Closed. Add `TicketStatus::stopsSlaClock(): bool` (Resolved||Closed) rather than changing
  > the meaning of `isTerminal()` (which gates other behavior). Do NOT repurpose `isTerminal()`.
- **SLA-04** SLA **state** is read-time, never stored:
  - no `due_at` → `null` (render "—").
  - `resolved_at !== null` → compare `resolved_at` vs `due_at`: `<=` → `on_track` (met), else
    `overdue` (breached but resolved). The clock is stopped at `resolved_at`.
  - else (live ticket) compare `now` vs `due_at`: `now >= due_at` → `overdue`; `now >= due_at − threshold`
    → `due_soon`; else `on_track`.
  - `DUE_SOON_THRESHOLD_HOURS` is a small constant on the VO/enum (e.g. **8** h; a fixed knob).
- **SLA-05** The board's "overdue only" filter = `due_at < now AND resolved_at IS NULL` (SQL `where`),
  so it never paginates wrong or N+1s. It deliberately does NOT include "resolved-but-was-late" rows
  (those are closed business; the operator cares about live breaches).
- **SLA-06** Colour pairing (WCAG 1.4.1): `on_track` emerald, `due_soon` amber, `overdue` red — always
  rendered **with** the text label, never colour alone.

---

## 4. ACCEPTANCE SCENARIOS (When … Then — falsifiable)

### Categories CRUD
- **AC-CAT-1** *Create.* When an agent submits the category form with `name="Billing"`, `color="emerald"`,
  Then a `categories` row exists with those values and `archived_at = null`, and it appears in the
  management list.
- **AC-CAT-2** *Name required + unique.* When an agent submits a blank name, Then an inline `name`
  validation error renders and nothing persists. When an agent submits a name that already exists
  (case-insensitive), Then an inline uniqueness error renders and no duplicate row is created.
- **AC-CAT-3** *Edit.* When an agent edits a category's color from `emerald` to `red`, Then the row's
  `color` is `red` and the new badge colour renders on the board for its tickets.
- **AC-CAT-4** *Archive / restore.* When an agent archives a category, Then `archived_at` is set, it
  disappears from the create-form + board selectors, but its tickets still show the (archived) badge.
  When restored, `archived_at` is null and it reappears in selectors.
- **AC-CAT-5** *XSS-safe name.* When a category is named `<script>alert(1)</script>`, Then the board and
  show page render it **escaped** as text (`&lt;script&gt;…`) via `{{ }}`, never executed, never `{!! !!}`.

### Category on a ticket
- **AC-TC-1** *Assign on create.* When an agent creates a ticket with a chosen category, Then
  `tickets.category_id` equals that category and the show page renders its badge.
- **AC-TC-2** *Optional.* When an agent creates a ticket with "No category", Then `category_id` is null
  and the show/board render a neutral "—" (no badge), with no error.
- **AC-TC-3** *Filter.* When the board has tickets in categories A and B and the agent selects category A
  in the category filter, Then only A's tickets show; B's are hidden; the paginator resets to page 1.
- **AC-TC-4** *Only active selectable.* When a category is archived, Then it is NOT an option in the
  create form's category select nor the board's category filter dropdown of *active* choices.

### SLA
- **AC-SLA-1** *due_at computed.* When an **Urgent** ticket is created at time `T`, Then `due_at == T + 4h`
  (== `created_at + slaHours(Urgent)`), within a 1-second tolerance.
- **AC-SLA-2** *Fresh ticket on track.* A freshly created Medium ticket Then reports `slaState() ==
  on_track` and renders the emerald SLA badge.
- **AC-SLA-3** *Old unresolved → overdue.* Given a **High** ticket whose `created_at` is 48h ago
  (`due_at` 24h in the past) and `resolved_at` is null, Then `slaState() == overdue`, the show page +
  board render the red OVERDUE badge, and it appears under the "overdue only" board filter.
- **AC-SLA-4** *due_soon.* Given a Medium ticket whose `due_at` is 4h in the future (inside the 8h
  threshold) and unresolved, Then `slaState() == due_soon` (amber).
- **AC-SLA-5** *Resolved before due is met.* Given an Urgent ticket created now, then transitioned to
  Resolved (so `resolved_at` is set, before `due_at`), Then `slaState() == on_track` (met), it is NOT
  `overdue`, and it does NOT appear under the "overdue only" filter.
- **AC-SLA-6** *Resolved after due is breached-but-met-clock-stopped.* Given a ticket whose `due_at` is
  in the past and is then resolved (so `resolved_at > due_at`), Then `slaState() == overdue` (the SLA was
  breached) BUT it still does NOT appear under the "overdue only" live-breach filter (because
  `resolved_at IS NOT NULL`).
- **AC-SLA-7** *Clock stops on terminal.* When `TransitionTicket` moves a ticket Open→…→Resolved, Then
  `resolved_at` is set to ~now. When it is re-opened (Resolved→Open), Then `resolved_at` is cleared.
- **AC-SLA-8** *Overdue filter SQL.* The "overdue only" filter narrows the paginated query in SQL
  (`due_at < now() AND resolved_at IS NULL`) — proven by a query-count / result-set assertion, not PHP
  post-filtering.

### Cross-cutting
- **AC-GATE-1** *Gating.* A guest hitting the category management route is redirected to `login`. All new
  routes sit behind `auth`.
- **AC-ARCH-1** The new code obeys the Law: `declare(strict_types=1)`, `final`, `readonly` VO/DTO,
  backed enums, Actions final, DTOs extend `Spatie\LaravelData\Data`, the Category model is final and
  extends Eloquent, `App\Domain` does not `use` `Illuminate\Http\Request` nor `Livewire\Component`.

---

## 5. NON-FUNCTIONAL

- **Performance (Ley 9):** the board list eager-loads `category:id,name,color` (no N+1 when rendering the
  category badge). SLA state is computed in PHP from already-loaded `due_at`/`resolved_at` columns (no
  extra query per row). The overdue filter is a SQL `where`. Indexes: `tickets(due_at)` and a partial /
  composite index supporting the live-breach filter; `categories(archived_at)` for the active scope.
- **Security:** category `name`/`description` are user input → rendered only with `{{ }}` auto-escape
  (AC-CAT-5); `color` is constrained to the allow-list enum so it can never inject markup into the Flux
  `color=` prop. CSRF via Livewire. Domain stays free of `Illuminate\Http`.
- **i18n / a11y:** all new UI copy via `__()` in `lang/{en,es}/*`; `SlaState` + `Category` (color has no
  label, the name is the label) badges pair colour **and** text. Dark/light inherited from `layouts.app`.
- **Reversibility:** every migration has a working `down()` (drop FK + columns, drop table).

---

## 6. DEFERRED — explicit seams (record, do not build)
- Automation/rules engine; business-hours SLA calendar; escalation delivery (email/push/queue);
  scheduled `due_soon` reminders; per-category SLA override column; ticket subject/body/priority edit
  (and the `due_at` recompute that priority-edit would imply); customer portal; category
  merge/reorder/icons/nesting; hard-delete of categories.
