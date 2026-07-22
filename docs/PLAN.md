# PLAN — Nexo ID

> Execution follows the `planning-by-stages` skill (alvaro standards repo): one numbered task at a time, checklist marked at the moment, SPEC before code, AC ↔ test traceability by name, one commit per task (`"N,M description"`), CI green before the next task, gate per phase with owner sign-off.
>
> Per the just-in-time rule, only the current phase is broken into tasks. Later phases list objective, key work, and gate criteria; their tasks get derived from their SPEC's acceptance criteria when the phase opens.

## Phase 0 — Planning & foundations

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
- [x] 2.7 **Reference client + guide** — a small client app on a distinct origin completes signup→authorize→token→userinfo end-to-end; integration guide for tool developers.
- [x] 2.8 **Build-gate prep** — `docs/ARCHITECTURE.md` updated; AC↔test sweep 16/16; negative-test audit; `audit-open-source` (keys/secrets never tracked).
- [x] 2.9 **[OWNER-GATED] Deploy** — **deployed 2026-07-20** to `nexoid.alvarocdev.com` (with Alvaro): code via deploy key, `.env`, `passport:keys` on server, `migrate --force`, storage symlink, prod caches, subdomain symlink, cron. Verified from outside: home/register/`/up` 200, discovery + JWKS 200 (https issuer), strict CSP beat LiteSpeed, HSTS present, attribution footer "powered by alvarocdev.com". Production email smoke passed (real SMTP verification mail received). **Backups + uptime monitoring deferred by owner** — to be set up across all Nexo tools together, before real users arrive (Phase 3, when Nexo Short brings the first registrations).

**Gate 2 (owner sign-off required):**
- [x] Build ACs green with name-traced tests (2.2–2.8): all 16 SPEC-sso ACs have ≥1 test, `grep` sweep is 1:1. Pest full suite 66 passed, 1 skipped (node-only i18n guardian).
- [x] Full flow exercised from an external origin incl. silent SSO (ReferenceFlowTest: signup→verify→authorize→token→userinfo with a client on `tool.example`); token/PKCE negative tests (wrong verifier, reused code, unknown client, non-exact redirect).
- [x] Pint + Larastan L6 + `composer audit` + translations `--check` + build all green (CI-equivalent 2026-07-20).
- [x] `audit-open-source`: clean — Passport RSA keys and `.env` never tracked (gitignored), no private keys / real APP_KEY in history.
- [x] **Deploy (task 2.9):** deployed and verified in production (HTTP + real OAuth flow + real SMTP verification email). Backups + uptime monitoring **deferred by owner** to a cross-tool ops pass before real users (Phase 3); tracked, not dropped.
- [x] Owner sign-off: **Alvaro, 2026-07-20** (with backups/uptime deferred as above).

## Phase 3 — First client: Nexo Short (current)

**Objective:** Nexo Short launches SSO-only against Nexo ID (ADR-004 §5) — the integration pattern is built once and reused.

SPEC: [SPEC-client.md](../SPEC-client.md) (numbered ACs). Opened 2026-07-21. Pattern form decided with Alvaro: **copyable template in the standards repo** (`templates/nexo-sso-client/`), not a Composer package — template ships its own Pest tests; consumers copy code + tests. Timing driver: Nexo Short is mid-plan and its Phase 5 (launch) needs both the pattern and T4.

- [ ] 3.1 **[OWNER-GATED] T4 ops pass** — verified backups (automated MySQL dumps per tool + at least one tested restore) + external uptime monitoring on `/up` for all production Nexo tools (nexoid, nexolinks, nexoagenda; nexoshort when it deploys). Cross-tool, done with Alvaro (hPanel, monitor account, dump destination). Hard precondition for real users.
- [ ] 3.2 **Client pattern template** — build `templates/nexo-sso-client/` in the standards repo per SPEC-client ACs: OIDC client (discovery, PKCE, state, id_token validation), `NEXO_SSO_*` config, `nexo_id_sub` migration stub, account linking, degradation, README, Pest tests (AC name-traced).
- [ ] 3.3 **Prove the template against a real local Nexo ID** — template test suite green against a locally running provider instance (not only mocks); AC↔test grep sweep 1:1.
- [ ] 3.4 **[OWNER-GATED] Nexo Short consumes it (coordinated)** — register Nexo Short's production client (`nexo:sso-client`, exact `nxo.li` redirect URIs) on the server; support that project's agent integrating the template per its own plan; never edited unilaterally from here.
- [ ] 3.5 **Pre-public cleanups** — genericize `DEPLOYMENT.md` (real hosting layout out), decide `nexo-id.md` (drop/move), SEO-base on public pages (meta description/OG/canonical/hreflang/sitemap), author-email check.
- [ ] 3.6 **[OWNER-GATED] Repo goes public** — full `audit-open-source` pass, then flip visibility with Alvaro.

**Gate 3 (owner sign-off required):** real signup→login→use flow on Nexo Short via Nexo ID from `nxo.li`; degradation verified (Nexo ID down → active sessions keep working); T4 in place (backups restore-tested + uptime alerts live); audit passed; repo public; owner sign-off.

## Phase 4 — Integration: Nexo Agenda & Nexo Links

**Objective:** both existing tools sign in via Nexo ID; Alvaro's instances reach SSO-only (per ADR-005 there is no user data to migrate — re-check counts at phase opening; safety valve in ADR-005 §4).

Key work per tool: additive `nexo_id_sub` migration, SSO client via the Phase 3 pattern, linking by verified email, switch the hosted instance to SSO-only. Tool order decided at phase opening.

**Gate 4 (per tool, then phase):** real user journey on both tools with one account; standalone (local-auth) mode still green in each tool's suite (self-host story intact); user-count re-check documented; owner sign-off.

## Phase 5 — Ecosystem polish & hardening

**Objective:** the visible ecosystem layer and post-migration hardening.

Key work (from SCOPE backlog, prioritized at phase opening): "your tools" account page; 2FA (TOTP + backup codes); back-channel/propagated logout evaluation; email change flow; user-visible security audit log. Nexo Events integrates whenever it's born (needs only the Phase 3 pattern, not this phase).

**Gate 5:** shipped items with their SPEC/AC/test discipline; full-ecosystem security re-audit; owner sign-off.
