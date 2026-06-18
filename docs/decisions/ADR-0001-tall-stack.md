# ADR-0001 — TALL stack with Livewire 4 + Flux 2

- Status: Accepted
- Date: 2026-06-18

## Context

FluxDesk is a helpdesk / ticketing app. The OMNIBUS programme runs two frontend
carriles: Path A (Inertia + React) and Path B (TALL — Livewire). FluxDesk is the
designated TALL chapter (`livewire-tall` vault module).

## Decision

Build on the **TALL stack**: Tailwind CSS v4 (Oxide) + Alpine 3 (bundled with
Livewire) + Laravel 12 + **Livewire 4** with **Flux UI v2**.

- Livewire **4.3.x** — current major (Jun 2026). Volt is absorbed into core as
  Single-File Components; we do NOT install `livewire/volt`.
- Full-page components are wired with `Route::livewire()`, never `Route::get()`.
- SFCs use the raw `<?php new class extends Component {} ?>` head (the `@php`
  Blade directive is NOT recognised by Livewire's SFC finder).
- Flux v2 for all primitives (keyboard- and screenreader-tested out of the box).

## Consequences

- Pin `livewire/livewire ^4.3`, `livewire/flux ^2.14`, Tailwind v4.2+.
- This deviates from the `tech-stack.md` "recomendado" carril (Livewire 3.6) in
  favour of the FluxDesk-specific `livewire-tall.md` module, which explicitly
  scopes Livewire 4 to this project.
- `APP_KEY` must be stable across deploys (Livewire 4 hashes the update endpoint
  URL with it).
