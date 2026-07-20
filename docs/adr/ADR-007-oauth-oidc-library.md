# ADR-007 — Phase 2 OAuth/OIDC server: Laravel Passport + openid-connect bridge

- **Date:** 2026-07-20
- **Status:** Accepted, except the specific bridge package.

> **Superseded in part by [ADR-008](ADR-008-oidc-bridge-correction.md) (2026-07-20):** the Passport-based approach stands, but the named bridge `ronvanderheijden/openid-connect` proved incompatible with Passport 13 (oauth2-server 8 vs 9). The bridge is now `jeremy379/laravel-openid-connect`. Read this ADR for *why Passport*; read ADR-008 for *which bridge*.

## Context

ADR-003 chose OAuth 2.0 authorization code + PKCE with an OIDC-style identity layer, and explicitly deferred to "the Phase 1 spike against the chosen Laravel library (Laravel Passport first candidate)" whether the MVP is fully OIDC-compliant or OAuth 2.0 + OIDC-shaped endpoints. Task 1.1 ran that spike (Composer index queries + sibling deploy notes; Hostinger runtime not exercisable from this session).

## Spike findings

- **Versions (current):** `laravel/framework` v13.20.0, `laravel/passport` v13.7.5, `league/oauth2-server` v9.4.1. Passport 13 wraps oauth2-server 9, which supports **authorization code + PKCE natively** — the exact flow ADR-003 requires.
- **OIDC:** Passport is OAuth 2.0 only (no `id_token`/`userinfo`/discovery). The maintained bridge **`ronvanderheijden/openid-connect` (v1.2.1)** adds the OIDC identity layer on top of Passport. So the ADR-003 question resolves to: **ship OIDC via this bridge**, falling back to a hand-rolled `userinfo` + `id_token` only if the bridge's Passport-version compatibility fails when Phase 2 opens.
- **Hostinger shared constraints** (from `deploy-laravel-hostinger` + sibling notes, to be re-verified live at Phase 2 deploy):
  - `proc_open`/`exec` disabled → keep the established `composer install --no-scripts` + manual `package:discover`; `storage:link` by hand. Passport's `passport:keys` writes an RSA keypair via `ext-openssl` (standard on Hostinger) and does **not** need `exec`; keys can also be generated in CI/locally and uploaded.
  - LiteSpeed "Force HTTPS" overrides the CSP → re-assert in `public/.htaccess` (already a known Phase 2 deploy step).
  - No Node on server → assets built in CI/locally and uploaded (already the sibling pattern).

## Decision

1. **Phase 2 uses Laravel Passport** as the OAuth 2.0 + PKCE server, with **`ronvanderheijden/openid-connect`** for the OIDC identity layer. Re-validate the bridge's Passport/Laravel compatibility as the first task when Phase 2 opens (just-in-time spike).
2. **Passport is NOT a Phase 1 dependency.** Phase 1 ships the standalone identity core only; introducing Passport belongs to Phase 2 to keep Phase 1 lean and its attack surface small.
3. No blocker found for the ADR-002/ADR-003 direction — Hostinger + Passport is viable with the existing deploy playbook.

## Consequences

- ADR-003's open question is closed: the MVP is **OIDC-shaped via the bridge**, not merely bare OAuth 2.0.
- Phase 1 scaffolding pins Laravel 13.x and hand-writes auth controllers/views (sibling pattern) rather than adding a starter-kit dependency — see SPEC reconciliation log.
- Phase 2 planning inherits a concrete, spiked library choice instead of an open question.
