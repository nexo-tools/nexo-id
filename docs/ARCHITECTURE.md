# ARCHITECTURE — Nexo ID

> What exists and where. Updated at each gate ("ARCHITECTURE matches reality"). Reflects the end of Phase 1 (standalone identity core; no SSO provider yet — that is Phase 2).

## Stack

Laravel 13 + MySQL, local dev via Sail (mysql + mailpit). Blade + Tailwind v4 (Vite), no Alpine. Pest 4 / Pint / Larastan level 6. CI: GitHub Actions (composer audit, Pint, Larastan, translations `--check`, npm build, Pest). See [ADR-002](adr/ADR-002-stack-and-hosting.md).

## Request pipeline

`bootstrap/app.php` appends two web middlewares: `SetLocale` (locale from `?lang` → session → `Accept-Language`, restricted to en/es/pt) then `SecurityHeaders` (self-contained CSP + headers, HSTS only over https).

## Domain model

- **User** (`app/Models/User.php`): uuid PK (`HasUuids`), `MustVerifyEmail`, fields `display_name`, `email` (normalized lowercase via set-mutator), `password` (hashed cast), `locale`, `email_verified_at`. Table in `database/migrations/0001_01_01_000000_create_users_table.php` (also `password_reset_tokens`, `sessions` with uuid `user_id`).
- **Sessions**: database driver; the `sessions` table backs both live sessions and the profile's session listing/revocation.

## Auth surface (routes/web.php)

| Area | Routes | Controllers | ACs |
|---|---|---|---|
| Registration | `GET/POST register` (guest, throttle 10/min) | `Auth/RegisteredUserController` + `RegisterRequest` | AC-REG-* |
| Email verification | `GET verify-email`, signed `GET verify-email/{id}/{hash}`, `POST email/verification-notification` (throttle 6/min) | `Auth/EmailVerificationPromptController`, `VerifyEmailController`, `EmailVerificationNotificationController` | AC-VERIFY-* |
| Login / logout | `GET/POST login` (guest), `POST logout` (auth) | `Auth/AuthenticatedSessionController` + `LoginRequest` (per-credential RateLimiter) | AC-LOGIN-* |
| Password recovery | `GET/POST forgot-password`, `GET reset-password/{token}`, `POST reset-password` (throttle 5/min) | `Auth/PasswordResetLinkController`, `NewPasswordController` | AC-PWD-* |
| Profile & sessions | `GET/PATCH profile`, `PUT profile/password`, `DELETE profile/sessions[/{id}]` (auth + **verified**) | `ProfileController`, `PasswordController`, `SessionController` | AC-PROFILE-*, AC-SESS-* |

Shared: `App\Actions\ChangeUserPassword` (sets password, sends `PasswordChanged` notification, revokes other DB sessions) is used by both password reset and profile password change (AC-PWD-6). `App\Support\SessionSummary` is the DTO for the session list.

## Views

`layouts/app` (header + locale switcher + footer) and `layouts/auth` (centered card). Partials: `partials/brand` (SVG mark), `partials/footer` (env-configurable attribution, neutral default → repo). Reusable `<x-field>` component. Auth views under `resources/views/auth/`, profile under `resources/views/profile/`.

## i18n

Base language English (source strings are the keys). `scripts/generate-translations.mjs` extracts `__()` literals and builds `lang/{es,pt}.json` from `scripts/translations/{es,pt}.json`; a guardian Pest test + CI `--check` fail on drift. Framework messages (validation, auth) resolve from vendor in English; es/pt of those are backlog (see SPEC reconciliation).

## Not here yet (later phases)

OAuth/OIDC provider (Passport + openid-connect bridge, Phase 2 — [ADR-007](adr/ADR-007-oauth-oidc-library.md)); deploy to `nexoid.alvarocdev.com`; 2FA; "your tools" page.
