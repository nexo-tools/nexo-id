# SPEC — Phase 1: Core identity service (standalone)

> Written before code (planning-by-stages). Governs Phase 1 of [docs/PLAN.md](docs/PLAN.md).
> Acceptance criteria are numbered and each maps to ≥1 test whose name cites the id (grep-able). SSO (OAuth/OIDC provider) is **out** — that is Phase 2; this phase de-risks it with the spike (task 1.1) but ships no provider endpoints.

## Purpose

A standalone, self-hostable identity application: users register, verify their email, log in/out, recover their password, manage their profile and active sessions. This is the account substrate the SSO provider (Phase 2) will sit on top of. It must satisfy the SCOPE security minimums as first-class acceptance criteria — public surface = attack surface.

## Scope

### In
Registration · email verification · login/logout · password recovery · profile (display name + password change) · active-session listing and revocation · rate limiting · security headers + CSP · i18n (en/es/pt) · brand assets + instance-configurable attribution · CI with dependency audit.

### Out (Phase 2+ / backlog)
OAuth/OIDC provider endpoints, client registration, silent SSO (Phase 2) · 2FA · social login · email-change flow · "your tools" account page (SCOPE backlog).

## Data model (Phase 1)

`users`: `id` (uuid), `email` (unique, citext-style case-insensitive match), `password` (bcrypt/argon2id hash), `display_name`, `email_verified_at` (nullable), `locale` (nullable), timestamps.

Sessions use Laravel's **database** session driver (`sessions` table: `id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) so sessions can be listed and revoked per user. Password resets use the framework `password_reset_tokens` table (hashed token, single-use by deletion, expiry).

## Acceptance criteria

### Registration — AC-REG
- **AC-REG-1**: A visitor registers with email + display name + password; on success an **unverified** user is created and a verification email is queued.
- **AC-REG-2**: Duplicate email (case-insensitive) is rejected with a validation error (422/redirect-back), never a 500.
- **AC-REG-3**: Password below policy (min 8 chars, confirmed) is rejected; a compliant password is accepted.
- **AC-REG-4**: The stored password is a bcrypt/argon2 hash — never the plaintext, and `password_verify` succeeds against it.
- **AC-REG-5**: Registration is rate limited per IP; the N+1th rapid attempt is throttled (429).

### Email verification — AC-VERIFY
- **AC-VERIFY-1**: The verification email carries a signed, expiring URL; visiting it sets `email_verified_at`.
- **AC-VERIFY-2**: A tampered or expired verification URL is rejected (403), leaving the account unverified.
- **AC-VERIFY-3**: An unverified user is blocked from the authenticated area (`verified` middleware) and sent to the verification notice.
- **AC-VERIFY-4**: Resending the verification email is rate limited.

### Login / logout — AC-LOGIN
- **AC-LOGIN-1**: Valid credentials establish an authenticated session and redirect to the intended area.
- **AC-LOGIN-2**: Unknown email and wrong password return **identical** generic errors (no user enumeration).
- **AC-LOGIN-3**: Repeated failed logins for the same email+IP are throttled (lockout after N attempts).
- **AC-LOGIN-4**: The session id is regenerated on login (no session fixation).
- **AC-LOGIN-5**: Logout invalidates the session and regenerates the CSRF token.
- **AC-LOGIN-6**: The session cookie is `HttpOnly` + `SameSite=Lax`, and `Secure` when served over HTTPS.

### Password recovery — AC-PWD
- **AC-PWD-1**: Requesting a reset for a known email sends a single-use, short-expiry link backed by a **hashed** token.
- **AC-PWD-2**: Requesting a reset for an unknown email returns the **same** generic response (no enumeration).
- **AC-PWD-3**: A valid token sets a new password; reusing the same token afterwards is rejected.
- **AC-PWD-4**: An expired or malformed token is rejected.
- **AC-PWD-5**: Recovery request and reset endpoints are rate limited.
- **AC-PWD-6**: On any password change (reset or profile), a notification email is sent to the account and the user's **other** sessions are invalidated.

### Profile & sessions — AC-PROFILE / AC-SESS
- **AC-PROFILE-1**: A user updates their display name; the change persists.
- **AC-PROFILE-2**: A user changes their password only by confirming the current one; a wrong current password is rejected.
- **AC-SESS-1**: A user views their active sessions (current one flagged, with ip/user-agent/last-active).
- **AC-SESS-2**: A user revokes a specific other session; that session can no longer authenticate.
- **AC-SESS-3**: A user revokes all other sessions in one action, keeping the current one.

### Security & platform — AC-SEC / AC-I18N
- **AC-SEC-1**: Every response carries the security headers and a self-contained CSP (no external hosts in the policy).
- **AC-SEC-2**: HSTS is advertised only over HTTPS, never over plain HTTP.
- **AC-I18N-1**: The app resolves locale by `?lang` → session → `Accept-Language`, restricted to en/es/pt.
- **AC-I18N-2**: A guardian test (and CI `--check`) fails if the generated translation files drift from source.

## Definition of done (Gate 1)
- Every AC above has ≥1 passing test whose name cites the id; `grep` sweep proves the mapping.
- Deliberate-violation checks exercised: rate limit actually returns 429; reused/expired reset token actually rejected; tampered verification URL actually 403.
- Pint + Larastan (level 6) + Pest all green in CI; dependency audit step present.
- `docs/ARCHITECTURE.md` matches reality.
- Owner sign-off stamped in PLAN.

## Reconciliation log
<!-- Deviations from this SPEC discovered during implementation get a dated note here (planning-by-stages rule 8), never silent divergence. -->
- **2026-07-20 (task 1.1 spike)** — Pinned Laravel 13.x + Passport 13.7.5 (PKCE-native) + `ronvanderheijden/openid-connect` bridge for Phase 2; recorded in [ADR-007](docs/adr/ADR-007-oauth-oidc-library.md). Consequences for Phase 1: (a) **Passport is not a Phase 1 dependency** — the standalone core ships without it; (b) auth controllers/requests/views are **hand-written following the nexo-agenda pattern** (vendored Breeze-style) instead of adding a starter-kit dependency, avoiding starter-kit version risk and matching the siblings. No AC changes.
