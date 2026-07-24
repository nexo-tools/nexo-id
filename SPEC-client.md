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
- **2026-07-22 (tasks 3.2 + 3.3 landed)** — Template built at `templates/nexo-sso-client/` and proven end-to-end against a real local Nexo ID (register→verify→silent-SSO→code→token→id_token-vs-JWKS→linked session→degradation). All 12 ACs name-traced (16 tests). Two client-side reconciliations forced by the real provider: (1) the OIDC bridge signs id_tokens **without a `kid`** header, so the client validates a kid-less single-key JWKS directly; (2) provider discovery forces `https` unless `OPENID_FORCE_HTTPS=false` (local-dev only). Provider-side, the run exposed and fixed the oauth uuid-column bug (see SPEC-sso reconciliation). Consumer (Nexo Short) integrated the template unmodified except the documented adaptation points and runs SSO-only in production; its callback is on the panel host (`nxo.li` is cookieless).
- **2026-07-24 (M4b — nonce + RP-initiated logout)** — Two defense-in-depth additions, both name-traced:
  - **AC-NONCE-1** (client): `redirect` mints a random `nonce` (like `state`) into the session and sends it to `/authorize`; the callback rejects an id_token whose `nonce` claim is missing or does not match. The bridge already echoes the nonce (`AuthCodeGrant` + `IdTokenResponse`), so no provider change was needed. Guards against replaying an id_token minted for a different authorize request.
  - **AC-LOGOUT-1** (client) / **end_session** (provider): the client keeps the raw id_token from login and exposes `nexo-sso.logout`, which ends the local session and front-channel-redirects to the provider's `end_session_endpoint` (from discovery) with `id_token_hint` + `post_logout_redirect_uri`; it degrades to a local-only logout when no endpoint is advertised or the provider is unreachable. Provider side, Nexo ID now defines the `openid.end_session_endpoint` route (`/oauth/logout`, in `routes/web.php` for session access) — discovery advertises it automatically. **Contract (anti open-redirect, ADR-009):** the endpoint ends the browser session, then honours `post_logout_redirect_uri` **only** when it is exactly a registered redirect URI of the client named in a signature-valid `id_token_hint` (expiry ignored — hints are routinely expired); no hint, unknown client, or an unregistered URI lands on Nexo ID's own "signed out" page. Never an open redirect. **Owner-gated:** each client must register its `post_logout_redirect_uri` (env `NEXO_SSO_POST_LOGOUT_REDIRECT_URI`) as one of its redirect URIs on the provider, or the provider refuses it.
