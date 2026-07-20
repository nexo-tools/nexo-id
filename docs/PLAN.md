# PLAN — Nexo ID

> Execution follows the `planning-by-stages` skill (alvaro standards repo): one numbered task at a time, checklist marked at the moment, SPEC before code, AC ↔ test traceability by name, one commit per task (`"N,M description"`), CI green before the next task, gate per phase with owner sign-off.
>
> Per the just-in-time rule, only the current phase is broken into tasks. Later phases list objective, key work, and gate criteria; their tasks get derived from their SPEC's acceptance criteria when the phase opens.

## Phase 0 — Planning & foundations (current)

**Objective:** decisions made and recorded, scope fixed, project formalized. Zero product code.

- [x] 0.1 Read the standards system + evaluation document (nexo-id.md); separate facts from assumptions to re-evaluate.
- [x] 0.2 `docs/SCOPE.md` — value proposition, MVP in/out, product principles, backlog.
- [x] 0.3 Foundational ADRs 001–005 (product model, stack/hosting, SSO protocol, tool auth model, user migration), status Proposed.
- [x] 0.4 `docs/PLAN.md` (this file) with phases and gates.
- [x] 0.5 Project formalization: `AGENTS.md` (EN), `CLAUDE.md` → AGENTS, `CLAUDE.local.md` (gitignored) with standards briefing, `README.md` with Status line, `.gitignore`, git init.
- [x] 0.6 Present plan + decisions to Alvaro; resolve open decisions; stamp sign-off.

**Gate 0 (owner sign-off required):**
- [x] ADRs 001–005 reviewed: 001–004 accepted as proposed; 005 amended and accepted — no registered users exist yet in either tool, so migration collapses to lazy account linking (no import machinery).
- [x] Open decisions resolved: repo slug **`nexo-id`** (sibling pattern); hosted subdomain **`nexoid.alvarocdev.com`** (consistent with nexolinks./nexoagenda.).
- [x] SCOPE MVP in/out approved.
- [x] Sign-off: **Alvaro, 2026-07-19** (decisions taken interactively at gate presentation).

## Phase 1 — Core identity service (standalone)

**Objective:** Nexo ID as a working standalone auth app (no SSO yet), built SPEC-first.

SPEC: [SPEC.md](../SPEC.md) (numbered ACs). Tasks (derived from the SPEC's ACs; one commit per task, `"1,N description"`, CI green before next):

- [x] 1.1 **Spike** — validate the OAuth2/OIDC server library (Laravel Passport first candidate) against Hostinger shared constraints (`proc_open`/`exec` disabled, LiteSpeed) and pin Laravel + auth-scaffold versions; reconcile SPEC + ADR-003 with findings. Throwaway code only; deliverable is a decision note in `docs/adr/` or the SPEC reconciliation log.
- [x] 1.2 **Scaffold** — Laravel (pinned) + Sail (mysql, mailpit) + Pest + Pint + Larastan L6 + CI mirroring nexo-agenda (Pint, Larastan, translations `--check`, build, Pest) + dependency audit step. Boots and `curl` returns 200.
- [x] 1.3 **Nexo conventions** — `config/nexo.php`; `SecurityHeaders` middleware + CSP + `SecurityHeadersTest` (AC-SEC-1/2); `SetLocale` middleware + i18n generator (en/es/pt) + guardian test + CI `--check` (AC-I18N-1/2); brand assets + `NEXO_ATTRIBUTION_*` env + footer; base layout.
- [x] 1.4 **Registration** — user model (uuid, case-insensitive email), register flow, password policy, hashing, per-IP throttle (AC-REG-1..5).
- [x] 1.5 **Email verification** — signed expiring link, `verified` gating, rate-limited resend (AC-VERIFY-1..4).
- [x] 1.6 **Login / logout** — enumeration-safe errors, email+IP throttle/lockout, session regeneration, cookie flags (AC-LOGIN-1..6).
- [x] 1.7 **Password recovery** — hashed single-use short-expiry tokens, enumeration-safe request, rate limiting, change-notification email + other-session invalidation (AC-PWD-1..6).
- [x] 1.8 **Profile & sessions** — display-name update, current-password-gated change, active-session list + per-session and bulk revoke via DB session driver (AC-PROFILE-1/2, AC-SESS-1..3).
- [x] 1.9 **Gate prep** — `docs/ARCHITECTURE.md`; AC↔test grep sweep; deliberate-violation checks; `audit-open-source` dry pass; branding footer.

**Gate 1 (owner sign-off required):**
- [x] All 30 ACs green with name-traced tests; `grep` AC↔test sweep is 1:1 (SPEC ids ↔ test names). Pest: 47 passed, 1 skipped (the i18n guardian skips only where node is absent; CI runs it + the standalone `--check`).
- [x] Deliberate-violation checks exercised: registration/login/verify/reset rate limits actually return 429/lockout; reused reset token rejected (AC-PWD-3); expired + tampered verification link 403 (AC-VERIFY-2); wrong current password rejected (AC-PROFILE-2); cross-user session revoke blocked (AC-SESS-2).
- [x] Pint + Larastan (level 6) + `composer audit` + translations `--check` + build all green (CI-equivalent run 2026-07-20).
- [x] `audit-open-source` dry pass: clean — no secrets in full history, `.env` never tracked, sqlite ignored, only `env()` config references; sole note is the author email domain `alvaro@mc4pc.com` (already accepted; repo private).
- [x] End-to-end on real MySQL via Sail: register→verify-gate, login (verified→profile, unverified→notice), reset token stored as bcrypt hash, profile flags current device.
- [x] `docs/ARCHITECTURE.md` matches reality.
- [x] Owner sign-off: **Alvaro, 2026-07-20** (green light to proceed to Phase 2).

## Phase 2 — SSO provider + production deploy

**Objective:** the OAuth 2.0 + PKCE / OIDC-style flow of ADR-003 live at `nexoid.alvarocdev.com`.

SPEC: [SPEC-sso.md](../SPEC-sso.md) (numbered ACs). Stack decided by the 2.1 spike: Passport 13 + `jeremy379/laravel-openid-connect` ([ADR-008](adr/ADR-008-oidc-bridge-correction.md)). Tasks (one commit per task, `"2,N description"`, CI green before next):

- [x] 2.1 **Spike** — validate the OIDC bridge against Passport 13 / Laravel 13. Finding: ADR-007's `ronvanderheijden/openid-connect` is incompatible (oauth2-server 8 vs 9); `jeremy379/laravel-openid-connect` 3.3 resolves clean. Recorded in ADR-008 + SPEC-sso reconciliation.
- [x] 2.2 **Provider setup** — install Passport 13 + bridge; publish/run migrations; `passport:keys` (gitignored); register the provider, OIDC scopes/claims (openid/profile/email → sub/email/email_verified/name); CI adjustments. Boots; discovery + JWKS reachable.
- [x] 2.3 **Client registration** — artisan command to create/list first-party clients; exact redirect-URI validation; seed a demo client (AC-CLIENT-1/2).
- [x] 2.4 **Authorization + PKCE + silent SSO** — authorize endpoint: code + PKCE, consent-free first-party, verified-email gate, login redirect (AC-AUTH-1..5, AC-CLIENT-2).
- [x] 2.5 **Token + OIDC** — token endpoint (code→tokens, PKCE verify, single-use); id_token/userinfo/discovery/JWKS; scope→claim gating (AC-TOKEN-*, AC-OIDC-*, AC-SCOPE-1).
- [x] 2.6 **Central logout** — logout ends the Nexo ID session; silent authorize then requires re-login (AC-LOGOUT-1).
- [ ] 2.7 **Reference client + guide** — a small client app on a distinct origin completes signup→authorize→token→userinfo end-to-end; integration guide for tool developers.
- [ ] 2.8 **Build-gate prep** — `docs/ARCHITECTURE.md`; AC↔test sweep; negative-test audit; `audit-open-source` (keys/secrets never tracked).
- [ ] 2.9 **[OWNER-GATED] Deploy** — `deploy-laravel-hostinger` to `nexoid.alvarocdev.com`; Passport keys on server; production SMTP; cron; verified backups (restore tested once); uptime monitoring; real end-to-end in prod. **Needs Alvaro's infrastructure/credentials.**

**Gate 2:** build ACs green with name-traced tests (2.2–2.8); full flow exercised from an external origin incl. silent SSO; token/PKCE negative tests; **then** deployed and verified in production (HTTP + real flow); backups restored once for real; owner sign-off.

## Phase 3 — First client: Nexo Short

**Objective:** Nexo Short launches SSO-only against Nexo ID (ADR-004 §5) — the integration pattern is built once here and reused.

Key work: reusable Laravel client pattern (OIDC client + `NEXO_SSO_*` env contract + local-session handling + graceful degradation); Nexo Short consumes it (coordinated with that project's own plan); `audit-open-source` + repo goes public no later than this phase.

**Gate 3:** real signup→login→use flow on Nexo Short via Nexo ID from `nxo.li`; degradation verified (Nexo ID down → active sessions keep working); audit passed; owner sign-off.

## Phase 4 — Integration: Nexo Agenda & Nexo Links

**Objective:** both existing tools sign in via Nexo ID; Alvaro's instances reach SSO-only (per ADR-005 there is no user data to migrate — re-check counts at phase opening; safety valve in ADR-005 §4).

Key work per tool: additive `nexo_id_sub` migration, SSO client via the Phase 3 pattern, linking by verified email, switch the hosted instance to SSO-only. Tool order decided at phase opening.

**Gate 4 (per tool, then phase):** real user journey on both tools with one account; standalone (local-auth) mode still green in each tool's suite (self-host story intact); user-count re-check documented; owner sign-off.

## Phase 5 — Ecosystem polish & hardening

**Objective:** the visible ecosystem layer and post-migration hardening.

Key work (from SCOPE backlog, prioritized at phase opening): "your tools" account page; 2FA (TOTP + backup codes); back-channel/propagated logout evaluation; email change flow; user-visible security audit log. Nexo Events integrates whenever it's born (needs only the Phase 3 pattern, not this phase).

**Gate 5:** shipped items with their SPEC/AC/test discipline; full-ecosystem security re-audit; owner sign-off.
