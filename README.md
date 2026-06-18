# FluxDesk

Helpdesk / ticketing application on the **TALL stack**
(Tailwind v4 · Alpine · Laravel 12 · Livewire 4 + Flux UI v2).

Part of the **OMNIBUS** programme · Chapter 4.6 · `fluxdesk.carlosgardea.com`.

---

## What it is

FluxDesk is a support-ticket workspace: requesters open tickets, agents triage by
priority and move them through a guarded status lifecycle. The foundation in this
repository ships:

- A **dashboard** full-page Livewire 4 component showing ticket counts by status.
- A **Ticketing domain** (DDD-Lite): `Ticket` model, `CreateTicket` /
  `TransitionTicket` actions, `CreateTicketData` DTO, `TicketStatus` /
  `TicketPriority` enums (with bilingual labels + a legal transition graph), and
  value objects (`Uuid` UUIDv7, `TicketSubject`).
- **Dark / light / system** theming with a FOUC guard, OKLCH design tokens, and
  WCAG 2.2 AA affordances (skip link, focus-visible, no colour-only state).
- **Bilingual (ES/EN)** user-facing copy via Laravel translation files.
- A baseline **security headers** middleware.
- A real **Pest 4** suite: architecture rules (enforcing the OMNIBUS Law), unit
  tests (enums / value objects), and feature tests (dashboard + create-ticket)
  running against **PostgreSQL 18**.

## Stack

| Layer        | Choice                                              |
| ------------ | --------------------------------------------------- |
| Runtime      | PHP 8.5                                              |
| Framework    | Laravel 12                                          |
| Frontend     | Livewire 4 + Flux UI v2 + Alpine 3 (bundled)        |
| CSS          | Tailwind CSS v4 (Oxide), CSS-first `@theme` tokens  |
| Build        | Vite 7 + `@tailwindcss/vite`                        |
| Database     | PostgreSQL 18 (UUIDv7 keys)                          |
| Cache/queue  | Valkey 8                                             |
| DTOs         | Spatie Laravel Data v4                               |
| Tests        | Pest 4 (arch + unit + feature)                      |
| Static       | PHPStan 2 + Larastan 3 · **level 10**               |
| Format       | Laravel Pint                                        |

Version decisions: see [`docs/decisions/`](docs/decisions/).

## Requirements

- Docker (for PostgreSQL 18 + Valkey 8 via `compose.yaml`, or Laravel Sail)
- PHP 8.5 + Composer 2 (or run Composer in the `composer:2` image)
- Node 24 + **pnpm** (never `npm` — OMNIBUS supply-chain rule)

## Getting started

```bash
# 1. Backing services (PostgreSQL 18 + Valkey 8)
docker compose up -d

# 2. Environment
cp .env.example .env
composer install
php artisan key:generate

# 3. Database
php artisan migrate --seed   # fictional demo data only (no real PII)

# 4. Frontend
pnpm install
pnpm run build               # or: pnpm run dev

# 5. Serve
php artisan serve            # http://localhost:8000
```

One-shot helpers:

```bash
composer setup   # install + env + key + migrate + pnpm install + build
composer dev     # serve + queue + logs + vite (concurrently)
```

## Quality gate

Run before every commit (and enforced in CI, `.github/workflows/ci.yml`):

```bash
composer format    # Laravel Pint
composer analyse   # PHPStan / Larastan level 10
composer test      # Pest 4 (unit + feature + arch)
```

Feature tests run against the **real PostgreSQL driver** (never SQLite). Point
`DB_*` at a test database (`fluxdesk_test`) — see `phpunit.xml`.

## Project layout

See [`ARCHITECTURE.md`](ARCHITECTURE.md) for the DDD-Lite domain structure and the
key decisions behind it.

## License

Proprietary — © Carlos Gardea. Not for redistribution.
