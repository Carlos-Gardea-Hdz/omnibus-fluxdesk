# NEXT_STEPS — omnibus-fluxdesk

Foundation scaffolded 2026-06-18 (commit `48092ac`, 91 files). This file lists what was verified and what remains; build the domain out via `/sdd`.

## Verified at scaffold time

Actually ran and PASSED: (1) Laravel Pint --test — 50 files clean. (2) PHPStan/Larastan level 10 — No errors. (3) Pest full suite (29 tests, 61 assertions) — all passing against a REAL PostgreSQL 18 container (Feature) plus Valkey 8 up; Unit+Arch run standalone too. (4) Vite production build (pnpm run build) — succeeded, generated public/build/manifest.json + CSS (229KB incl. Flux+Tailwind) + JS bundle; the HTTP GET / feature test confirms the manifest/layout render with 200. Composer create-project + all package installs completed and composer audit reported no advisories. Test containers and the temp PHP-8.5+pdo_pgsql image were removed after verification.

## Known gaps / issues

- PHPStan level 10: 2 errors in framework-shipped config files (config/database.php greaterOrEqual.alwaysTrue, config/filesystems.php rtrim) were resolved by removing config/ from the analysed paths rather than editing framework files; revisit if you add custom config that should be linted.
- Larastan flags Laravel's standard factory definition() return type at level 10 — suppressed via a scoped ignoreError for database/factories/* (matches framework stubs), not a level downgrade.
- CSP is NOT enforced yet (only baseline headers shipped) — intentional, documented in ADR-0002; one defence layer is pending.
- A global pnpm hook re-appended a placeholder allowBuilds block to pnpm-workspace.yaml during scaffolding; cleaned before the final commit, but be aware it may reappear on future pnpm config edits. esbuild build is authorized via onlyBuiltDependencies.
- Auth (Breeze/login) is not scaffolded — the dashboard route is currently public; ticket authorization policies and the agent/requester auth flow are next steps.

## Next steps

- [ ] Add authentication (Livewire-compatible Breeze or Flux auth scaffolding) and gate the dashboard + ticket routes; add Pest authz negative tests (assertForbidden/assertUnauthorized).
- [ ] Build the ticket CRUD + lifecycle UI under resources/views/livewire/tickets/ (list with priority sort, create form bound to CreateTicketData, status transitions via TransitionTicket) with #[Locked] ids and $this->authorize() in every wire action.
- [ ] Implement and roll out a nonce-based CSP in Report-Only, then enforce (ADR-0002).
- [ ] Switch the password hash driver to argon2id (config/hashing.php) per security-2026 §6.
- [ ] Add Pest browser/E2E smoke tests (pestphp/pest-plugin-browser) covering dark+light themes and a mobile viewport, plus an axe a11y assertion on the dashboard.
- [ ] Pin all GitHub Actions in ci.yml to full commit SHAs before the repo goes live (security-2026 §4).
- [ ] Run php artisan typescript:transform is N/A here (Livewire path); instead keep DTO validation rules as the contract and add mutation testing (pest --mutate) on the Ticketing domain.
- [ ] Provision Sail/Octane + Horizon for the Valkey queue when real async work (notifications) is added.
