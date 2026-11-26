# ARCHITECTURE — Nexo ID

> What exists and where. Updated at each gate ("ARCHITECTURE matches reality"). Reflects Phase 2 complete: the SSO provider is implemented, tested, and **deployed to production** (`nexoid.alvarocdev.com`, task 2.9, 2026-07-20; the uuid oauth-column fix followed on 2026-07-22).

## Stack

Laravel 13 + MySQL, local dev via Sail (mysql + mailpit). Blade + Tailwind v4 (Vite), no Alpine. Pest 4 / Pint / Larastan level 6. CI: GitHub Actions (composer audit, Pint, Larastan, translations `--check`, npm build, Pest). See [ADR-002](adr/ADR-002-stack-and-hosting.md).

## Request pipeline

`bootstrap/app.php` appends to the web group, in order: `SetLocale` (locale from `?lang` → the shared `nexo-lang` cookie → `Accept-Language`, restricted to en/es/pt), `SecurityHeaders` (self-contained CSP + headers, HSTS only over https), `NexoNoStore` + `NoStoreForSensitiveResponses` (no-store), `EnsureIdpSessionActive` (ADR-010), then the two session-reading checks of `/oauth/authorize`: `RequireVerifiedForAuthorize` and `SignUpOnPromptCreate` (see «SSO provider»).

## Domain model

- **User** (`app/Models/User.php`): uuid PK (`HasUuids`), `MustVerifyEmail`, fields `display_name`, `email` (normalized lowercase via set-mutator), `password` (hashed cast), `locale`, `email_verified_at`. Table in `database/migrations/0001_01_01_000000_create_users_table.php` (also `password_reset_tokens`, `sessions` with uuid `user_id`).
- **Sessions**: database driver; the `sessions` table backs both live sessions and the profile's session listing/revocation.
- **IdP sessions (ADR-010)**: `idp_sessions` (one row per login on a device: the OIDC `sid`, started by `App\Auth\StartIdpSessionOnLogin` on every `Login`, «remember me» included) and `idp_session_clients` (which clients got a code in each sid). `App\Auth\IdpSessions` starts, ends and fans out; `EnsureIdpSessionActive` signs out a browser whose sid was ended elsewhere. «Remember me» lasts 30 days (`guards.web.remember`).

## Auth surface (routes/web.php)

| Area | Routes | Controllers | ACs |
|---|---|---|---|
| Registration | `GET/POST register` (guest, throttle 10/min); reached from a tool by `prompt=create` (names the tool, «Para entrar a …») | `Auth/RegisteredUserController` + `RegisterRequest`, `App\Auth\SignUpDestination` | AC-REG-*, AC-CREATE-* |
| Email verification | `GET verify-email`, signed `GET verify-email/{id}/{hash}`, `POST email/verification-notification` (throttle 6/min) | `Auth/EmailVerificationPromptController`, `VerifyEmailController`, `EmailVerificationNotificationController` | AC-VERIFY-* |
| Login / logout | `GET/POST login` (guest), `POST logout` (with or without a session), `POST logout/everywhere` (auth), `GET signed-out` («Cerraste sesión en todo Nexo»; signed in → `/profile`) | `Auth/AuthenticatedSessionController` + `LoginRequest` (per-credential RateLimiter); `SessionController@destroyAll`; a `419` on a sign-out form → `App\Http\ExpiredSignOut` (redirect to the confirmation) | AC-LOGIN-*, AC-BCL-P3 |
| Password recovery | `GET/POST forgot-password`, `GET reset-password/{token}`, `POST reset-password` (throttle 5/min) | `Auth/PasswordResetLinkController`, `NewPasswordController` | AC-PWD-* |
| Profile & sessions | `GET/PATCH profile`, `PUT profile/password`, `DELETE profile/sessions[/{id}]`, `DELETE profile` (delete the account, password-confirmed, throttle 5/min) (auth + **verified**) | `ProfileController` (+ `App\Auth\ConnectedTools` for «Herramientas conectadas», `App\Actions\DeleteAccount`), `PasswordController`, `SessionController` | AC-PROFILE-*, AC-SESS-* |

Shared: `App\Actions\ChangeUserPassword` (sets password, sends `PasswordChanged` notification, revokes other DB sessions and their IdP sessions) is used by both password reset and profile password change (AC-PWD-6). `App\Support\SessionSummary` is the DTO for the session list.

## Views

`layouts/app` (header + locale switcher + footer) and `layouts/auth` (centered card). Partials: `partials/brand` (SVG mark), `partials/footer` (env-configurable attribution, neutral default → repo). Reusable `<x-field>` component. Auth views under `resources/views/auth/`, profile under `resources/views/profile/`.

## i18n

Base language English (source strings are the keys). `scripts/generate-translations.mjs` extracts `__()` literals and builds `lang/{es,pt}.json` from `scripts/translations/{es,pt}.json`; a guardian Pest test + CI `--check` fail on drift. Framework messages (validation, auth, passwords) have their es/pt in `lang/{es,pt}/{auth,passwords,validation}.php` (2026-11-26, H-nexoid-12).

## SSO provider (Phase 2)

Laravel Passport 13 + `jeremy379/laravel-openid-connect` ([ADR-008](adr/ADR-008-oidc-bridge-correction.md)). Auto-discovery of both packages is disabled (`composer.json` `dont-discover`) so `App\Providers\OidcServiceProvider` — which extends the bridge's provider (itself extending Passport's) — is the sole provider. It injects the bridge's `IdTokenResponse` and auth-code grant, through our subclasses `App\Oidc\IdTokenResponse` and `App\Oidc\AuthCodeGrant`, which add the `sid` claim and register the client against the IdP session ([ADR-010](adr/ADR-010-session-control.md)). Registering two of these providers at once would register Passport's routes twice and break `route:cache`.

- **Endpoints:** `GET /oauth/authorize` (code + PKCE), `POST /oauth/token`, `GET /oauth/userinfo`, `GET /.well-known/openid-configuration`, `GET /oauth/jwks`, `GET|POST /oauth/logout` (end_session; the POST is the confirmation when there is no valid `id_token_hint`).
- **Back-channel logout (ADR-010):** `App\Auth\Backchannel\LogoutTokenIssuer` signs logout_tokens; `BackchannelLogoutDispatcher` posts them in parallel (2 s) and queues failures as `App\Jobs\DeliverBackchannelLogout` (backoff until 24 h). Client URIs live in `oauth_clients.backchannel_logout_uri` (`nexo:sso-client --backchannel=`, `nexo:sso-client:update`).
- **Keys:** RSA keypair in `storage/oauth-*.key` (gitignored; generated per environment via `passport:keys`).
- **Clients:** `App\Models\OauthClient` overrides `skipsAuthorization()` → first-party (owner-less) clients are consent-free (silent SSO). Registered via the `nexo:sso-client` artisan command (public/PKCE, exact redirect URIs).
- **Consent screen:** a functional consent view (`resources/views/auth/oauth/authorize.blade.php`, registered via `Passport::authorizationView`) lists requested scopes and is shown to any client that does *not* skip authorization; first-party clients bypass it.
- **Verified gate:** `RequireVerifiedForAuthorize` redirects unverified users away from `/oauth/authorize` only (pass-through elsewhere), keeping that authorize request as `url.intended` so verifying the email resumes it — unless the intended URL is already an authorize request of the same client, e.g. its `prompt=create` (parameters read as plain strings only). It is appended to the **`web` group** (`bootstrap/app.php`, after `EnsureIdpSessionActive`), not to `config('passport.middleware')`: Passport's route middleware runs *before* the web group, with the session not started, so from there it read no user and — until 2026-11-26 — unverified accounts got codes (the tests' `actingAs()` hid it; `AuthorizationTest` now pins the effective order). Only session-free checks stay in `config/passport.php` (`ThrottleAuthorizeByIp`, `EnforcePkceS256`, `NoStoreForSensitiveResponses`).
- **`prompt=create` (v2.2, «alta que vuelve a la tool»):** `SignUpOnPromptCreate` (web group, after the verified gate) turns Passport's «sign in first» redirect for a guest into `/register`, with the authorize URL minus `create` as `url.intended`; registration → email verification (`VerifyEmailController` already returns to the intended URL) → the authorize resumes → code. With a session `create` is ignored, `none` wins. `App\Auth\SignUpDestination` names the tool on the sign-up screen only for a genuine intended authorize URL (same origin, registered non-revoked client with that exact redirect_uri), using `ConnectedTools::describe()`. Discovery advertises `prompt_values_supported` (`none login consent create`).
- **end_session confirmation:** «Mantener la sesión abierta» goes to the tool's `url + entry` when the request's `client_id` is a tool of `config/nexo-ecosystem.php` (`ConnectedTools::describe()`, non-revoked), else `/profile`.
- **Claims:** `App\Entities\IdentityEntity` maps scopes→claims (`profile`→name, `email`→email/email_verified); the bridge filters by granted scope. Scopes declared via `Passport::tokensCan(config('openid.passport.tokens_can'))` in `AuthServiceProvider`.
- **User:** `HasApiTokens` + `OAuthenticatable`; the `api` guard (driver `passport`) protects userinfo.
- **Integration guide:** [INTEGRATION.md](INTEGRATION.md).

## Not here yet (later phases)

Production is live (task 2.9, 2026-07-20). Still deferred: backups + uptime monitoring (owner-deferred to a cross-tool ops pass before real users, PLAN Gate 3); Gate 3 (Nexo Short consuming the provider end-to-end); third-party client onboarding/polish (the consent screen itself already exists — see the SSO provider section); 2FA; "your tools" page.
