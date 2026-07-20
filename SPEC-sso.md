# SPEC — Phase 2: SSO provider (OAuth 2.0 + PKCE / OIDC)

> Written before code (planning-by-stages). Governs Phase 2 of [docs/PLAN.md](docs/PLAN.md). Builds on the Phase 1 identity core ([SPEC.md](SPEC.md)).
> Stack: Laravel Passport 13 + `jeremy379/laravel-openid-connect` ([ADR-008](docs/adr/ADR-008-oidc-bridge-correction.md)), per ADR-003's authorization-code + PKCE + OIDC decision.
> Each AC maps to ≥1 test whose name cites the id (grep-able). **Production deploy** is a separate, owner-gated task (2.9) — its verification is part of Gate 2 but not of these build ACs.

## Purpose

Turn the standalone identity core into an **SSO provider**: any Nexo tool (on any domain, stack, or host — including `nxo.li` and self-hosted instances) can sign a user in through Nexo ID using standard OAuth 2.0 authorization code + PKCE with an OIDC identity layer. First-party clients get **silent SSO** (an active Nexo ID session signs them in with no consent screen).

## Scope

### In
Client registration/management · authorization endpoint (code + PKCE, silent SSO for first-party, verified-email gate) · token endpoint (code→tokens, PKCE verification, single-use codes) · OIDC id_token + userinfo + discovery + JWKS · scopes/claims (openid, profile, email) · central logout · a reference client proving the flow from a different origin · integration guide.

### Out (later / backlog)
Consent UI for third-party clients (first-party are consent-free in MVP; third-party consent is backlog) · back-channel/propagated logout to clients (Phase 5) · dynamic client registration · refresh-token rotation policy tuning · 2FA in the flow (Phase 5).

## Data model additions

Passport tables (`oauth_auth_codes`, `oauth_access_tokens`, `oauth_refresh_tokens`, `oauth_clients`, `oauth_*`) via its migrations. `oauth_clients.user_id` is uuid-compatible (Passport 13 supports uuid client owners). Passport signing keys live outside git (`storage/`, gitignored) — generated per environment.

## Acceptance criteria

### Client registration — AC-CLIENT
- **AC-CLIENT-1**: A first-party client can be created (name, redirect URI, PKCE/public — no secret required) via an artisan command; it is marked first-party (consent-free).
- **AC-CLIENT-2**: The authorize endpoint accepts only an **exact** registered redirect URI for the client; any mismatch is rejected (no open redirect).

### Authorization — AC-AUTH
- **AC-AUTH-1**: `GET /oauth/authorize` with a valid first-party client + PKCE `code_challenge`, when the user has an active **verified** session, issues an authorization code redirect to the registered `redirect_uri` with the returned `state` — **no consent screen** (silent SSO).
- **AC-AUTH-2**: An unauthenticated authorize request redirects to login and, after login, returns to complete the authorization.
- **AC-AUTH-3**: A logged-in but **unverified** user cannot obtain a code — they are sent to the verification notice.
- **AC-AUTH-4**: Authorize rejects an unknown `client_id`.
- **AC-AUTH-5**: Authorize requires PKCE (`code_challenge`) for public clients; a request without it is rejected.

### Token — AC-TOKEN
- **AC-TOKEN-1**: `POST /oauth/token` (authorization_code grant) with a valid code + matching `code_verifier` returns `access_token`, `id_token`, `token_type=Bearer`, and `expires_in`.
- **AC-TOKEN-2**: A wrong `code_verifier` (PKCE mismatch) is rejected.
- **AC-TOKEN-3**: Reusing an authorization code is rejected (single-use).

### OIDC identity — AC-OIDC
- **AC-OIDC-1**: `GET /oauth/userinfo` with a valid access token returns `sub` (= user uuid), and, per granted scope, `email` + `email_verified` and `name`.
- **AC-OIDC-2**: The `id_token` is a JWT signed with the provider key, with `iss`, `aud` (client id), `sub` (user uuid), `exp`; verifiable against the JWKS.
- **AC-OIDC-3**: `GET /.well-known/openid-configuration` returns a discovery document pointing at the authorize/token/userinfo/jwks endpoints; `GET` on the JWKS endpoint returns the public key set.
- **AC-OIDC-4**: `sub` equals the user's uuid and is stable across separate logins for the same user.

### Scopes — AC-SCOPE
- **AC-SCOPE-1**: Without the `email` scope, `userinfo`/`id_token` omit email claims; without `profile`, they omit `name`. `openid` is required for the id_token.

### Logout — AC-LOGOUT
- **AC-LOGOUT-1**: Central logout ends the Nexo ID session; a subsequent silent authorize no longer issues a code without re-login. (Tokens already issued remain valid until expiry — documented; back-channel logout is Phase 5.)

## Definition of done (build gate, part of Gate 2)
- Every AC above has ≥1 passing name-traced test; `grep` sweep proves the mapping.
- Negative/deliberate-violation tests exercised: PKCE mismatch, reused code, unknown client, redirect-uri mismatch, unverified user, missing scope.
- A reference client app (distinct origin) completes signup→authorize→token→userinfo end-to-end.
- Pint + Larastan + `composer audit` + translations `--check` + build + Pest all green.
- `docs/ARCHITECTURE.md` updated; integration guide written.

## Owner-gated (rest of Gate 2, task 2.9)
Deploy to `nexoid.alvarocdev.com` (Passport keys on server, `deploy-laravel-hostinger`); production SMTP; cron; verified backups (restore tested once); uptime monitoring; real end-to-end flow in production. Requires Alvaro's infrastructure/credentials.

## Reconciliation log
- **2026-07-20 (task 2.1 spike)** — OIDC bridge corrected to `jeremy379/laravel-openid-connect` (ADR-008); `ronvanderheijden/openid-connect` is incompatible with Passport 13. What the bridge provides vs. what we configure/build is reconciled here as task 2.2 lands.
