# ADR-0002 — Baseline security headers, CSP deferred

- Status: Accepted
- Date: 2026-06-18

## Context

security-2026 §10 mandates a strict set of security response headers, including a
nonce-based Content-Security-Policy. Livewire injects inline scripts and the Vite
dev server behaves differently from the production build, so a strict CSP needs
careful, per-response nonce wiring before it can be enforced.

## Decision

Ship a `SecurityHeaders` middleware now with the safe, always-correct headers:
`X-Content-Type-Options`, `Referrer-Policy`, `Cross-Origin-Opener-Policy`,
`Cross-Origin-Resource-Policy`, `Permissions-Policy`, and `Strict-Transport-Security`
(only over HTTPS, staged `max-age` without `preload`).

**CSP is intentionally deferred.** When the asset graph is final:

1. Generate a fresh CSPRNG nonce per response.
2. Roll out in `Content-Security-Policy-Report-Only` with a reporting endpoint.
3. Enforce only after violation traffic is clean.

## Consequences

- No app breakage from a premature strict CSP.
- A follow-up task owns CSP enforcement; until then we are missing one defence
  layer (documented, not forgotten). HSTS `preload` is also deliberately omitted
  until every subdomain is audited for HTTPS.
