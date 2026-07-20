# ADR-008 — Phase 2 spike: OIDC bridge is `jeremy379/laravel-openid-connect` (Passport 13-compatible)

- **Date:** 2026-07-20
- **Status:** Accepted (Phase 2 task 2.1 spike)
- **Supersedes:** the OIDC-bridge choice in [ADR-007](ADR-007-oauth-oidc-library.md) §1 (the rest of ADR-007 stands).

## Context

ADR-007 chose `ronvanderheijden/openid-connect` as the OIDC layer on top of Laravel Passport, with the standing instruction (ADR-003 §1, ADR-007 §1) to re-validate compatibility when Phase 2 opens. Task 2.1 ran that validation with real Composer resolution against the Phase 1 project (Laravel 13).

## Spike findings

- **`ronvanderheijden/openid-connect` (all versions ≤1.2.1) require `league/oauth2-server ^8.2`.** Laravel Passport **13.7** requires `league/oauth2-server ^9.2`. These cannot coexist — Composer resolution fails ("only one version of league/oauth2-server can be installed"). The ADR-007 bridge is **incompatible with Passport 13**.
- **`jeremy379/laravel-openid-connect` 3.3.0** — a maintained fork of the same bridge — resolves **cleanly** with `laravel/passport 13.7.5` + `league/oauth2-server 9.4.1` on the Laravel 13 project (dry-run: 15 locks, no conflicts). `oauth2-server 9.4.1` also supports PHP 8.5 (the 8.1–8.4 cap was only on 9.2.0).

## Decision

1. **Use `jeremy379/laravel-openid-connect` (^3.3) as the OIDC bridge** over Laravel Passport 13, instead of `ronvanderheijden/openid-connect`. Everything else in ADR-003/ADR-007 is unchanged: OAuth 2.0 authorization code + PKCE with an OIDC identity layer (id_token, userinfo, discovery, JWKS), on Laravel + Passport.
2. The ADR-003 fallback (hand-roll the OIDC layer if no compatible bridge exists) is **not needed** — a compatible maintained bridge exists.
3. Confirm the bridge boots (service provider, migrations, `passport:keys`) as the first action of task 2.2; if it fails at runtime despite resolving, revisit toward the ADR-003 hand-rolled fallback.

## Consequences

- No architecture change; the wire protocol clients depend on (authorization code + PKCE + OIDC endpoints) is identical. Off-the-shelf OIDC clients still integrate.
- ADR-007 remains the record of *why Passport*; this ADR corrects only *which bridge*. Future readers: the bridge is `jeremy379/laravel-openid-connect`.
