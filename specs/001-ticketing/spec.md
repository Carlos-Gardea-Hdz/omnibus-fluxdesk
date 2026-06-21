# Spec 001 — Helpdesk Ticketing Core (TALL)

**Status:** Draft
**Slice:** 001-ticketing
**Date:** 2026-06-21
**Branch:** feature/tall-foundation
**Stack:** Laravel 12 · Livewire 4 · Flux 2 · Tailwind v4 · Spatie Data 4 · PostgreSQL 18 · Valkey · Pest

---

## 1. WHAT & WHY

FluxDesk is a helpdesk (Biblia §4.6). The foundation scaffolded the **Ticketing domain**
(Ticket model, CreateTicket/TransitionTicket Actions, TicketStatus/TicketPriority enums,
TicketSubject VO, a public dashboard SFC) but ships **no authentication** and **no ticket UI**
(NEXT_STEPS: the auth gap, ticket CRUD/lifecycle UI). This slice delivers the **MVP an agent
actually uses**: log in, see the ticket board, open a ticket, work it through its lifecycle,
assign it, and comment on it.

**Why now:** without auth the board is world-readable; without the UI the domain is unreachable.
This slice closes both gaps and turns the scaffold into a working helpdesk console.

**Primary actor:** a support **agent** (an authenticated `User`). Requesters are represented as
`User` rows referenced by a ticket's `requester_id`; in this slice requesters do **not** log in
(no customer portal — deferred).

---

## 2. SCOPE

### In scope
1. **Agent authentication (minimal):** Livewire/Flux login + logout; a seeded demo agent; every
   ticket route gated behind `auth`.
2. **Ticket board:**
   - **Create** a ticket (Livewire form → `CreateTicketData` → `CreateTicket` Action).
   - **List/filter** tickets (Livewire table: filter by status + priority + free-text search,
     paginated, eager-loaded, status/priority badges via enum `color()`/`label()`).
   - **View** a ticket (detail).
   - **Transition** status (Flux buttons → `TransitionTicket`; only enum-allowed targets shown;
     illegal move → graceful inline error, never a 500).
   - **Assign** a ticket to an agent (`AssignTicket` Action).
   - **Comment** on a ticket (`TicketComment` model + `CommentOnTicket` Action; agent-authored;
     listed chronologically).

### Out of scope — DEFERRED (note only, do not build)
- SLA timers / breach tracking.
- Email-to-ticket ingestion.
- Customer/requester portal & requester self-service login.
- Self-registration, passkeys/WebAuthn, password reset, email verification.
- Ticket edit (subject/body/priority mutation after creation) — only status/assignment/comments mutate here.
- Real-time push / notifications / queues.
- File attachments on tickets or comments.

---

## 3. DOMAIN RULES (authoritative)

- **Status lifecycle** is the existing `TicketStatus` graph — do NOT redefine it:
  - Open → {InProgress, Pending, Closed}
  - InProgress → {Pending, Resolved, Closed}
  - Pending → {InProgress, Resolved, Closed}
  - Resolved → {Closed, Open}
  - Closed → {Open}
  - A transition is legal **iff** `current->canTransitionTo(target)`; an illegal move throws
    `InvalidTicketTransition` (fail loud).
- **Priority**: existing `TicketPriority` (low/medium/high/urgent) with `weight()` for ordering.
- **Requester** is set server-side at creation; **never** trusted from client input.
- **Assignee** is an agent `User` or null. Assigning sets `assignee_id`. Unassigning (to null) is allowed.
- **Comment** belongs to a ticket and to an authoring agent (`author_id`); body 1–5000 chars; never blank.
- **Money:** none in this slice.

---

## 4. ACCEPTANCE SCENARIOS (When … Then — falsifiable)

### Auth & gating
- **AUTH-1** Given a guest, When they request any ticket route (`/tickets`, `/tickets/create`,
  `/tickets/{id}`), Then they are redirected to `/login` (302).
- **AUTH-2** Given a seeded agent with valid credentials, When they submit the login form,
  Then they are authenticated and redirected to `/tickets`.
- **AUTH-3** Given wrong credentials, When they submit the login form, Then login fails with an
  inline error and no session is established.
- **AUTH-4** Given an authenticated agent, When they invoke logout, Then the session is cleared
  and they are redirected to `/login`.
- **AUTH-5** Given an authenticated agent at `/login`, When the page loads, Then they are
  redirected to `/tickets` (already-authenticated guard).

### Create
- **CREATE-1** Given an authenticated agent, When they submit a valid create form
  (subject ≥3, body ≥3, a priority), Then a ticket is persisted with status=Open, the requester
  is the authenticated agent, and they are redirected to the new ticket's detail page; the ticket
  appears in the list.
- **CREATE-2** Given an invalid form (subject "no", body "x"), When submitted, Then inline field
  errors render and **nothing is persisted** (ticket count unchanged).
- **CREATE-3** The `TicketOpened` event is dispatched on a successful create.

### List / filter
- **LIST-1** Given tickets in several statuses, When the agent filters by status=Open, Then only
  Open tickets are shown (`assertSee` open subjects, `assertDontSee` others).
- **LIST-2** Given tickets of several priorities, When filtered by priority=Urgent, Then only
  Urgent tickets are shown.
- **LIST-3** Given a search term matching a subject substring, When entered, Then only matching
  tickets are shown.
- **LIST-4** Given more tickets than the page size, When the list renders, Then it is paginated
  (page size = 15) and issues no N+1 (requester/assignee eager-loaded).
- **LIST-5** Status and priority each render as a Flux badge using the enum `color()` and the
  locale-aware `label()`.

### View / transition
- **TRANS-1** Given an Open ticket, When the agent transitions it to InProgress (an allowed
  target), Then status becomes InProgress and the detail reflects it; the transition succeeds with
  no error.
- **TRANS-2** Given an Open ticket, When a transition to Resolved (NOT allowed from Open) is
  attempted, Then a graceful inline error is shown, the status is unchanged, and no 500 occurs.
- **TRANS-3** The detail page renders **only** the enum-allowed transition buttons for the
  ticket's current status (e.g. an Open ticket shows In progress / Pending / Closed, never Resolved).

### Assign
- **ASSIGN-1** Given a ticket and an agent, When the agent assigns it to themselves (or another
  agent), Then `assignee_id` is set and the detail shows the assignee.
- **ASSIGN-2** Given an assigned ticket, When unassigned (assignee → none), Then `assignee_id`
  is null.

### Comment
- **COMMENT-1** Given a ticket, When the agent posts a non-blank comment, Then it is persisted with
  `author_id` = the agent, appears in the comment list (newest or oldest order — chronological),
  and the textarea clears.
- **COMMENT-2** Given a blank/whitespace comment, When submitted, Then an inline error renders and
  no comment is persisted.

---

## 5. NON-FUNCTIONAL / LAW

- `declare(strict_types=1)` in every PHP file; `final`; `readonly` VO/DTO; backed enums; UUIDv7 PKs.
- Validation SSOT = Spatie Data DTOs; Livewire components stay thin and delegate to Domain Actions.
- Domain layer (`app/Domain`) MUST NOT depend on `Illuminate\Http`; Livewire components live in
  `app/Livewire` is N/A — this scaffold uses **SFCs** under `resources/views/livewire/*` (the
  component class is the anonymous `new class extends Component` in the `.blade.php`). Keep that pattern.
- Bilingual UI copy (ES/EN) via `lang/*` + the existing `__()` + `app()->getLocale()` pattern;
  enums already return bilingual `label()`.
- Dark/light mode already handled by the layout — new views MUST use the same surface/text tokens.
- Migrations reversible. PHPStan level 10 clean. Pint clean. WCAG: never colour-only state.
- Reuse existing `CreateTicket`/`TransitionTicket`/enums/VO/`Ticket` — do NOT duplicate.

---

## 6. OPEN QUESTIONS / DECISIONS TAKEN

- **Agent role:** a full RBAC system is out of scope. Decision: add a nullable `is_agent` boolean
  (default true at seed) OR a `role` column is overkill for one slice — **decision: gate on `auth`
  only** (any authenticated `User` is an agent for this slice). A dedicated agent role is NOTED as a
  follow-up; no role migration in this slice unless implementation finds a blocker. (See plan §
  "Agent identity".)
- **Comment ordering:** chronological ascending (oldest first, conversation style). Falsifiable in tests.
- **Login default redirect:** `/tickets`.
