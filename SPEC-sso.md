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
Consent UI for third-party clients (first-party are consent-free in MVP; third-party consent is backlog) · dynamic client registration · refresh-token rotation policy tuning · 2FA in the flow (Phase 5).

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
- **AC-LOGOUT-1**: Central logout ends the Nexo ID session; a subsequent silent authorize no longer issues a code without re-login. (Tokens already issued remain valid until expiry; the tools' sessions are ended by the back-channel, AC-BCL-P*.)

### Session control — ADR-010 (added 2026-11-26)
- **AC-SID-1**: The id_token carries `sid` and `nonce`; issuing the code registers the client against that sid.
- **AC-SID-2**: A password login starts the sid, and `session()->regenerate()` does not change it.
- **AC-SID-3**: A session opened before session control gets a sid lazily on its first authorize.
- **AC-SID-4**: A login through the «remember me» cookie also starts a sid.
- **AC-SID-5**: A browser whose sid was ended elsewhere is signed out on its next request.
- **AC-SID-6**: The registry prunes sessions ended >30 days ago and open ones idle >45 days.
- **AC-BCL-P1**: Discovery announces `backchannel_logout_supported` and `backchannel_logout_session_supported`; `NEXO_OIDC_BACKCHANNEL=false` removes them.
- **AC-BCL-P2**: The logout_token follows Back-Channel Logout 1.0 (form POST, `typ: logout+jwt`, RS256 verifiable with the JWKS, `iss`/`aud`/`iat`/`exp`=iat+120/uuid `jti`/`sub`/`sid`/`events`, no `nonce`), with a fresh `jti` per token.
- **AC-BCL-P3**: Each event of ADR-010 §4 ends exactly its sids and notifies only the clients of those sids that registered a URI (logout, end_session with hint, «Cerrar esta sesión», «Cerrar las demás», «todos los dispositivos», password change/reset, account deletion with `sub`-only tokens); ending a sid twice notifies once.
- **AC-BCL-P4**: A client that is down does not block the sign-out (parallel 2 s attempt) and is queued with backoff for 24 h; a definitive 4xx is not retried; more than `sync_max` deliveries go straight to the queue; each retry mints a fresh token.
- **AC-BCL-P5**: The kill-switch sends and queues nothing, and sids still end.
- **AC-BCL-P6**: end_session without a valid hint (none, forged, or another account's) asks for confirmation; only the CSRF-protected POST signs out.
- **AC-CLIENT-3**: `nexo:sso-client --backchannel=` stores the back-channel URI (absolute http(s) only).
- **AC-CLIENT-4**: `nexo:sso-client:update` sets/removes the back-channel URI and adds redirects, idempotently, printing the final state.
- **AC-CSP-1**: `form-action` is `'self'` plus the origin of every other tool in the ecosystem registry (and `.htaccess` matches it, AC-SEC-1).
- **AC-NOSTORE-1**: `Cache-Control: no-store, private` on signed-in responses, the account entry points and the OAuth surface; discovery and JWKS stay cacheable.
- **AC-REMEMBER-1..3**: «Remember me» lasts 30 days; the pre-policy cookies are cut once (migration) and on demand (`nexo:rotate-remember-tokens`, idempotent); every revocation rotates the token while the requesting device keeps its cookie.
- **AC-RATE-3**: The authorize ceiling comes from `NEXO_OIDC_AUTHORIZE_PER_MINUTE`.

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
- **2026-07-22 (task 3.3 e2e)** — Schema correction: Passport's published migrations left `oauth_*.user_id`/`oauth_clients.owner_id` as bigint against uuid users; sqlite-based AC tests couldn't see it, a real-MySQL provider run did (authorize 500/truncation). Fixed by `2026_07_22_013500_fix_oauth_user_id_columns_for_uuid_users`; deployed to production 2026-07-22 (verified via `migrate:status`). Also noted: discovery forces https unless `OPENID_FORCE_HTTPS=false` (local dev), and id_tokens carry no `kid` — clients must handle a kid-less single-key JWKS.
- **2026-11-26 (ADR-010 — session control)** — Back-channel logout moved from "Out" to built: sid registry, `sid` in the id_token, signed logout_tokens with parallel inline delivery and queued retries, confirmation for end_session without a valid hint, «Cerrar sesión en todos los dispositivos», 30-day «remember me» with a one-time cut, `form-action` with the tools' origins (the CSP was blocking the login → tool hop), `no-store`, env-tunable authorize ceiling. New ACs above, name-traced in `tests/Feature/Sso/SessionControlTest.php`, `tests/Feature/Auth/RememberMeTest.php`, `tests/Feature/NoStoreTest.php`, `SecurityHeadersTest`, `ClientRegistrationTest` and `AuthorizationTest`. Behaviour change pinned in AC-LOGOUT-2/5: end_session without a hint no longer signs out on a bare GET.
