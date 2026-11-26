# ADR-010 — Nexo ID owns the ecosystem session (sid + back-channel logout)

- **Date:** 2026-11-26
- **Status:** Accepted (decisions D1–D10 approved by Alvaro on 2026-11-26). Supersedes ADR-009 §2–3; ADR-009 §1
  (RP-initiated logout) stays and is extended here.
- **Scope:** this repo (the provider side). The client side is the `nexo-sso-client` template in the standards
  repo, propagated to the five tools; it follows the same contract.

## Context

- Five SSO clients are live (nexotools, nexoevents, nexoagenda, nexolinks, nexoshort). ADR-009's trigger for
  back-channel logout (≥3 live clients) had fired.
- Alvaro asked for Nexo ID to control the session at all times: switching tools keeps you signed in, and signing
  out signs you out everywhere — otherwise there is a real security gap.
- The 2026-11-24 audit verified in Chromium and WebKit, in production:
  - the RP-initiated logout did not end Nexo ID: the tools' CSP `form-action 'self'` blocked the redirect;
  - no logout reached the other tools, neither from a tool nor from Nexo ID;
  - the interactive login stalled on Nexo ID's own `form-action 'self'`: the sign-in POST redirects to
    `/oauth/authorize`, which redirects to the tool, and the browser refused that last hop;
  - revoking sessions or changing the password in Nexo ID did not touch the tools, and revoking did not rotate
    the «remember me» token;
  - «remember me» lasted ~400 days (the framework default);
  - authenticated pages were cacheable, so the back button showed them after signing out.
- Constraints: shared hosting (no websockets, the queue is drained by cron every minute, no `proc_open`); tools
  are standalone by default (ADR-004); the alvarocdev.com subdomains are one site, nxo.li and self-hosts are not.

## Decision

1. **CSP `form-action`.** Nexo ID allows `'self'` plus the origin of every other tool in the ecosystem registry
   (`config/nexo-ecosystem.php`), computed in `SecurityHeaders` and written literally in `public/.htaccess` (the
   one LiteSpeed serves in production; `SecurityHeadersHtaccessSyncTest` keeps both equal). Tools allow their
   issuer's origin (template side).
2. **IdP session id (`sid`).**
   - Every login on the web guard — password **and** «remember me» cookie, both fire `Login` — starts a sid
     (`Str::random(40)`), stored in the session under `nexo_idp.sid` and in `idp_sessions`. It lives in the session
     data, so `session()->regenerate()` does not change it.
   - A session opened before this release gets one lazily on its first authorize.
   - Issuing an authorization code registers `(sid, client_id)` in `idp_session_clients` and writes the sid into
     the encrypted code payload; the id_token copies it as the `sid` claim. This extends the OIDC bridge the same
     way it already threads `nonce`: `App\Oidc\AuthCodeGrant` and `App\Oidc\IdTokenResponse`, wired by
     `App\Providers\OidcServiceProvider`, which replaces the bridge's provider (the bridge is now in
     `dont-discover`, like Passport). A test pins that the id_token carries `sid` and `nonce`, so a bridge upgrade
     that breaks it fails in CI.
   - A browser whose sid was ended elsewhere is signed out of Nexo ID on its next request
     (`EnsureIdpSessionActive`). With the database session driver the revocations also delete the session rows;
     with any other driver this is the only lock, so revocation does not depend on the driver.
3. **OIDC Back-Channel Logout 1.0.**
   - `oauth_clients.backchannel_logout_uri`, set with `nexo:sso-client --backchannel=` or
     `nexo:sso-client:update <client_id> --backchannel= [--add-redirect=]` (idempotent, prints the final state).
   - **logout_token:** RS256 with the id_token's key and header set (today without `kid`, like the id_token —
     single-key JWKS), `typ: logout+jwt`; claims `iss` (as in the id_token), `aud` (client_id), `iat`,
     `exp` = iat + 120, `jti` (uuid v4), `sub`, `sid`, `events: {"http://schemas.openid.net/event/backchannel-logout": {}}`,
     never `nonce`.
   - **Delivery** (`BackchannelLogoutDispatcher`): `POST` form-encoded `logout_token=…` to every client that had a
     code in the ended sid and registered a URI. One inline attempt per client, **all in parallel** with a 2 s
     timeout, so the person waits ~2 s at worst however many tools there are. With more than 10 deliveries in one
     event, everything goes straight to the queue. A transient failure (no connection, timeout, 5xx, 408/429) is
     queued (`DeliverBackchannelLogout`): first retry after 1 min, then 5, 15, 60 min and hourly, until 24 h after
     the logout, each attempt with a fresh token. A definitive refusal (any other 4xx) is logged and dropped: retrying
     cannot fix it, and the tool's 15-minute re-validation still closes that session.
   - Discovery announces `backchannel_logout_supported` and `backchannel_logout_session_supported`.
   - Kill-switch `NEXO_OIDC_BACKCHANNEL=false`: nothing is sent or queued and discovery stops announcing it; sids
     are still recorded and ended.
4. **What ends what** (all in `App\Auth\IdpSessions`):

   | Event in Nexo ID | Sids that end | Also |
   |---|---|---|
   | `POST /logout` | this device's | — |
   | `GET /oauth/logout` with a valid `id_token_hint` | the hint's and this device's | redirects to `post_logout_redirect_uri` only if it is a registered redirect URI of the hint's client |
   | `GET /oauth/logout` without a valid hint, with a session | none until confirmed: «¿Cerrar sesión en todo Nexo?», a CSRF-protected `POST /oauth/logout` | mitigates the forced logout by link (S9) |
   | «Cerrar esta sesión» (session list) | that session's (read from its database row) | rotates the remember token; this device keeps its cookie, re-issued |
   | «Cerrar las demás sesiones» | every sid of the account but this one | same rotation and re-issue |
   | «Cerrar sesión en todos los dispositivos» (`POST /logout/everywhere`, new) | every sid, this one included | rotates the remember token |
   | Password change / reset | change: all but this one; reset: all | rotates the remember token (it already did); a change re-issues this device's cookie |
   | Account deletion (no UI yet) | all | plus a `sub`-only logout_token to every client the account ever used (`IdpSessions::endForAccountDeletion`, to be called **before** the user row is deleted) |

   A hint is valid when its signature verifies against our key (expiry is ignored — logout hints are routinely
   expired) and, if the browser has a session, its `sub` is that account: a genuine id_token of *another* account
   (an attacker's own) cannot sign the victim out without the confirmation either.
   Ending a sid twice notifies once.
5. **«Remember me» lasts 30 days** (`guards.web.remember` = 43 200 min, read by `AuthManager` in Laravel 13). The
   cookies issued under the old policy are cut **once**, by the data migration
   `2026_11_26_000003_cut_remember_tokens_issued_under_the_400_day_policy`, which runs in the deploy's
   `migrate --force` and never again. `php artisan nexo:rotate-remember-tokens` does the same on demand (it nulls
   every token, prints how many, and is idempotent). Open sessions are not signed out by it.
6. **`Cache-Control: no-store, private`** on every response to a signed-in session, on the account entry points
   (login, register, password recovery, verification) and on the whole OAuth surface (`passport.middleware`
   and userinfo). Discovery and the JWKS stay cacheable.
7. **Rate limit of authorize** to env: `NEXO_OIDC_AUTHORIZE_PER_MINUTE` (default 60), because the tools'
   re-validation multiplies authorize calls behind a shared IP.
8. **Registry hygiene:** `nexo:prune-idp-sessions`, daily in the scheduler, drops sessions ended more than 30 days
   ago and open ones idle for 45 (longer than «remember me»).
9. **Not used:** front-channel logout and a shared cookie (ADR-009 and the 2026-11-24 analysis).

## Consequences

- **Positive:** signing out of any app signs the device out of Nexo ID and every tool in seconds; revoking in
  Nexo ID reaches every device, «remember me» included; a forged logout link can no longer sign anyone out of
  everything; the back button shows nothing private; it is standard OIDC, usable by third-party clients and
  self-hosts.
- **Costs:** two tables, a listener, a middleware, two bridge extensions and a provider subclass; one POST per tool
  per logout; one indexed read per request that carries a sid.
- **Bridge coupling:** `AuthCodeGrant`, `IdTokenResponse` and `OidcServiceProvider` mirror
  jeremy379/laravel-openid-connect 3.x internals. `SessionControlTest` (AC-SID-1) fails if an upgrade breaks them.
- **`form-action` only knows the registry.** A third-party client outside `config/nexo-ecosystem.php` would hit the
  same CSP wall after a password login (or after the consent POST). When one exists, its origin goes into the
  registry or the policy learns to read registered redirect URIs.
- **`POST /logout` still rotates the remember token** (Laravel's `logout()`), so other devices of the account lose
  their «remember me» cookie (not their open session). Kept on purpose: it is the framework's safe default and it
  only costs those devices a password after their session expires.
- **Refresh tokens** produce id_tokens without `sid` (there is no code to read it from). The tools never refresh:
  they re-authorize.
- **Standalone and self-hosts:** nothing changes for a deployment with no clients registered with a URI; the
  kill-switch turns the fan-out off.

## Rollout

1. Deploy Nexo ID (this ADR). It is additive: it records sids and sends nothing to clients without a
   `backchannel_logout_uri`. The deploy runs the three migrations, including the one-time «remember me» cut.
2. Deploy the template in each tool; once its endpoint is live in production, register it:
   `php artisan nexo:sso-client:update <client_id> --backchannel=https://<tool>/auth/nexo/backchannel-logout
   --add-redirect=https://<tool>/auth/nexo/signed-out`.
3. Verify with the session journeys (Chromium and WebKit) in production with the test account.
