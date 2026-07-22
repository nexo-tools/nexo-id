# SPEC — Phase 3: Reusable OIDC client pattern (first consumer: Nexo Short)

> Written before code (planning-by-stages). Governs Phase 3 of [docs/PLAN.md](docs/PLAN.md). Builds on the Phase 2 SSO provider ([SPEC-sso.md](SPEC-sso.md)).
> The pattern is built as a **copyable template in the standards repo** (`templates/nexo-sso-client/`) — decided with Alvaro 2026-07-21 (no Composer package: no versioning/maintenance overhead; each tool copies and adapts, like `templates/dev-environment/`). The template ships with its own Pest tests; consumers copy code + tests, so AC↔test traceability lives in the template and in each consumer's suite.
> Each AC maps to ≥1 test whose name cites the id (grep-able), in the template's test suite (proven against a local Nexo ID before Nexo Short consumes it).

## Purpose

Any Laravel-based Nexo tool (starting with Nexo Short on `nxo.li`) signs users in through a Nexo ID instance with a **standard, copy-in client**: OIDC authorization code + PKCE against the discovery document, a fixed `NEXO_SSO_*` env contract, local-session establishment with account linking, and graceful degradation when the provider is unreachable. Built once, reused by every tool (Nexo Agenda/Links in Phase 4, Nexo Events post-MVP).

## Scope

### In
`NEXO_SSO_*` env contract · runtime endpoint discovery · authorize redirect (PKCE + `state`) · callback: state check, code→token exchange, id_token validation against JWKS · userinfo fetch · local account creation/linking by `sub` and verified email · local session (tool-owned, independent lifetime) · local logout · graceful degradation (provider down) · template README (install/adapt steps) · template Pest tests.

### Out (later / backlog)
SSO-only enforcement toggles per tool (each tool's own plan decides; ADR-004) · back-channel logout (Phase 5) · refresh-token-driven session extension (tools own their sessions; re-auth is a fresh silent SSO) · non-Laravel client stacks (INTEGRATION.md covers them generically).

## Env contract

`NEXO_SSO_ENABLED` (bool) · `NEXO_SSO_ISSUER` (e.g. `https://nexoid.alvarocdev.com`) · `NEXO_SSO_CLIENT_ID` (uuid from `nexo:sso-client`) · redirect URI derived from the tool's own route (registered exactly on the provider). No client secret (public client, PKCE).

## Acceptance criteria

### Configuration & discovery — AC-CFG
- **AC-CFG-1**: With `NEXO_SSO_ENABLED=false` (or unset), the tool exposes no SSO routes/UI and touches no network — standalone mode is the default and stays fully functional.
- **AC-CFG-2**: Endpoints come from `{issuer}/.well-known/openid-configuration` at runtime (cached); no hard-coded provider paths.

### Login flow — AC-FLOW
- **AC-FLOW-1**: "Continue with Nexo ID" redirects to the discovered authorization endpoint with `response_type=code`, the configured client_id, exact redirect URI, `scope=openid profile email`, a random `state`, and a S256 PKCE challenge.
- **AC-FLOW-2**: The callback rejects a missing/mismatched `state` (CSRF) without touching the token endpoint.
- **AC-FLOW-3**: A valid callback exchanges the code (with the stored `code_verifier`) and validates the `id_token`: signature against JWKS, `iss` = configured issuer, `aud` = client_id, `exp` in the future. Any failure aborts login with a safe error (no session, no 500).

### Account linking — AC-LINK
- **AC-LINK-1**: First SSO login with an unknown `sub` and no matching local account creates a local user from the claims and stores the `sub` (additive `nexo_id_sub` column).
- **AC-LINK-2**: First SSO login where a local account exists with the same email links it **only if** `email_verified` is true in the claims; otherwise login is refused with a clear message (no silent takeover).
- **AC-LINK-3**: Subsequent logins match by `sub` (stable id), even if the email changed on Nexo ID.

### Local session — AC-SESS
- **AC-SESS-1**: A successful callback establishes the tool's own session; the tool's authenticated surface works with no further provider calls.
- **AC-SESS-2**: Local logout ends only the tool session (central logout is the provider's; documented in the template README).

### Degradation — AC-DEGRADE
- **AC-DEGRADE-1**: With the provider unreachable, already-authenticated sessions keep working (no per-request provider dependency).
- **AC-DEGRADE-2**: With the provider unreachable, starting a login fails gracefully: friendly error, no 500, no hang beyond a short timeout.

## Definition of done (build part of Gate 3)
- Template `templates/nexo-sso-client/` in the standards repo: client code, `NEXO_SSO_*` config, migration stub (`nexo_id_sub`), README, Pest tests covering every AC above (name-traced, grep sweep 1:1).
- Tests proven green against a local Nexo ID instance (real provider, not only mocks) before Nexo Short consumes the template.
- Standards-repo checks per its own conventions; this repo's suite stays green (Phase 3 adds no provider code).

## Owner-gated (rest of Gate 3)
Register Nexo Short's production client (`nexo:sso-client`, exact `nxo.li` redirect URIs) on the server · real signup→login→use flow on Nexo Short from `nxo.li` · degradation verified live · T4 ops pass (verified backups + uptime monitoring, cross-tool) · `audit-open-source` + repo goes public · owner sign-off.

## Reconciliation log
- **2026-07-21 (phase opening)** — SPEC created; pattern form decided (standards-repo template, not a package). To reconcile as tasks land.
