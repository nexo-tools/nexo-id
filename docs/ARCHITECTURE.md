# ARCHITECTURE — Nexo ID

> What exists and where. Updated at each gate ("ARCHITECTURE matches reality"). Reflects Phase 2 complete: the SSO provider is implemented, tested, and **deployed to production** (`nexoid.alvarocdev.com`, task 2.9, 2026-07-20; the uuid oauth-column fix followed on 2026-07-22).

## Stack

Laravel 13 + MySQL, local dev via Sail (mysql + mailpit). Blade + Tailwind v4 (Vite), no Alpine. Pest 4 / Pint / Larastan level 6. CI: GitHub Actions (composer audit, Pint, Larastan, translations `--check`, npm build, Pest). See [ADR-002](adr/ADR-002-stack-and-hosting.md).

## Request pipeline

`bootstrap/app.php` appends two web middlewares: `SetLocale` (locale from `?lang` → session → `Accept-Language`, restricted to en/es/pt) then `SecurityHeaders` (self-contained CSP + headers, HSTS only over https).

## Domain model

- **User** (`app/Models/User.php`): uuid PK (`HasUuids`), `MustVerifyEmail`, fields `display_name`, `email` (normalized lowercase via set-mutator), `password` (hashed cast), `locale`, `email_verified_at`. Table in `database/migrations/0001_01_01_000000_create_users_table.php` (also `password_reset_tokens`, `sessions` with uuid `user_id`).
- **Sessions**: database driver; the `sessions` table backs both live sessions and the profile's session listing/revocation.
- **IdP sessions (ADR-010)**: `idp_sessions` (one row per login on a device: the OIDC `sid`, started by `App\Auth\StartIdpSessionOnLogin` on every `Login`, «remember me» included) and `idp_session_clients` (which clients got a code in each sid). `App\Auth\IdpSessions` starts, ends and fans out; `EnsureIdpSessionActive` signs out a browser whose sid was ended elsewhere. «Remember me» lasts 30 days (`guards.web.remember`).

## Auth surface (routes/web.php)

| Area | Routes | Controllers | ACs |
|---|---|---|---|
| Registration | `GET/POST register` (guest, throttle 10/min) | `Auth/RegisteredUserController` + `RegisterRequest` | AC-REG-* |
| Email verification | `GET verify-email`, signed `GET verify-email/{id}/{hash}`, `POST email/verification-notification` (throttle 6/min) | `Auth/EmailVerificationPromptController`, `VerifyEmailController`, `EmailVerificationNotificationController` | AC-VERIFY-* |
| Login / logout | `GET/POST login` (guest), `POST logout`, `POST logout/everywhere` (auth) | `Auth/AuthenticatedSessionController` + `LoginRequest` (per-credential RateLimiter); `SessionController@destroyAll` | AC-LOGIN-*, AC-BCL-P3 |
| Password recovery | `GET/POST forgot-password`, `GET reset-password/{token}`, `POST reset-password` (throttle 5/min) | `Auth/PasswordResetLinkController`, `NewPasswordController` | AC-PWD-* |
| Profile & sessions | `GET/PATCH profile`, `PUT profile/password`, `DELETE profile/sessions[/{id}]` (auth + **verified**) | `ProfileController`, `PasswordController`, `SessionController` | AC-PROFILE-*, AC-SESS-* |

Shared: `App\Actions\ChangeUserPassword` (sets password, sends `PasswordChanged` notification, revokes other DB sessions and their IdP sessions) is used by both password reset and profile password change (AC-PWD-6). `App\Support\SessionSummary` is the DTO for the session list.

## Views

`layouts/app` (header + locale switcher + footer) and `layouts/auth` (centered card). Partials: `partials/brand` (SVG mark), `partials/footer` (env-configurable attribution, neutral default → repo). Reusable `<x-field>` component. Auth views under `resources/views/auth/`, profile under `resources/views/profile/`.

## i18n

Base language English (source strings are the keys). `scripts/generate-translations.mjs` extracts `__()` literals and builds `lang/{es,pt}.json` from `scripts/translations/{es,pt}.json`; a guardian Pest test + CI `--check` fail on drift. Framework messages (validation, auth) resolve from vendor in English; es/pt of those are backlog (see SPEC reconciliation).

## SSO provider (Phase 2)

Laravel Passport 13 + `jeremy379/laravel-openid-connect` ([ADR-008](adr/ADR-008-oidc-bridge-correction.md)). Auto-discovery of both packages is disabled (`composer.json` `dont-discover`) so `App\Providers\OidcServiceProvider` — which extends the bridge's provider (itself extending Passport's) — is the sole provider. It injects the bridge's `IdTokenResponse` and auth-code grant, through our subclasses `App\Oidc\IdTokenResponse` and `App\Oidc\AuthCodeGrant`, which add the `sid` claim and register the client against the IdP session ([ADR-010](adr/ADR-010-session-control.md)). Registering two of these providers at once would register Passport's routes twice and break `route:cache`.

- **Endpoints:** `GET /oauth/authorize` (code + PKCE), `POST /oauth/token`, `GET /oauth/userinfo`, `GET /.well-known/openid-configuration`, `GET /oauth/jwks`, `GET|POST /oauth/logout` (end_session; the POST is the confirmation when there is no valid `id_token_hint`).
- **Back-channel logout (ADR-010):** `App\Auth\Backchannel\LogoutTokenIssuer` signs logout_tokens; `BackchannelLogoutDispatcher` posts them in parallel (2 s) and queues failures as `App\Jobs\DeliverBackchannelLogout` (backoff until 24 h). Client URIs live in `oauth_clients.backchannel_logout_uri` (`nexo:sso-client --backchannel=`, `nexo:sso-client:update`).
- **Keys:** RSA keypair in `storage/oauth-*.key` (gitignored; generated per environment via `passport:keys`).
- **Clients:** `App\Models\OauthClient` overrides `skipsAuthorization()` → first-party (owner-less) clients are consent-free (silent SSO). Registered via the `nexo:sso-client` artisan command (public/PKCE, exact redirect URIs).
- **Consent screen:** a functional consent view (`resources/views/auth/oauth/authorize.blade.php`, registered via `Passport::authorizationView`) lists requested scopes and is shown to any client that does *not* skip authorization; first-party clients bypass it.
- **Verified gate:** `config/passport.php` registers `RequireVerifiedForAuthorize` on Passport routes; it redirects unverified users away from `/oauth/authorize` only (pass-through elsewhere).
- **Claims:** `App\Entities\IdentityEntity` maps scopes→claims (`profile`→name, `email`→email/email_verified); the bridge filters by granted scope. Scopes declared via `Passport::tokensCan(config('openid.passport.tokens_can'))` in `AuthServiceProvider`.
- **User:** `HasApiTokens` + `OAuthenticatable`; the `api` guard (driver `passport`) protects userinfo.
- **Integration guide:** [INTEGRATION.md](INTEGRATION.md).

## Not here yet (later phases)

Production is live (task 2.9, 2026-07-20). Still deferred: backups + uptime monitoring (owner-deferred to a cross-tool ops pass before real users, PLAN Gate 3); Gate 3 (Nexo Short consuming the provider end-to-end); third-party client onboarding/polish (the consent screen itself already exists — see the SSO provider section); 2FA; "your tools" page.
