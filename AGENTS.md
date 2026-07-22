# Nexo ID

> Entry point for any AI/agent working on this project. It follows Alvaro's standards system (repo `alvaro`, alvarocdev.com). Keep this file updated: persist here the important context that comes up during work sessions.
> This repo will be public: no secrets, credentials, or sensitive infrastructure details here.

## What this project is

Central identity service (SSO) for the Nexo ecosystem (Nexo Links, Nexo Agenda, Nexo Short, Nexo Events): one account, every tool. Open source, multi-instance, self-hostable, like its siblings. **Current state: Phases 1 & 2 signed off — the SSO provider (OAuth2+PKCE/OIDC) is LIVE at https://nexoid.alvarocdev.com. Phase 3 opened 2026-07-21 (reusable OIDC client pattern + Nexo Short as first consumer + T4 ops + repo goes public).** Start at [docs/PLAN.md](docs/PLAN.md); specs are [SPEC.md](SPEC.md) (core) + [SPEC-sso.md](SPEC-sso.md) (provider) + [SPEC-client.md](SPEC-client.md) (client pattern); map is [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md); tool integration is [docs/INTEGRATION.md](docs/INTEGRATION.md).

## Stack

Laravel 13 + MySQL (ADR-002). Blade + Tailwind v4 (Vite), no Alpine. Pest 4 / Pint / Larastan level 6. Local dev: app runtime via Sail; stateful services (MySQL, Mailpit) come from the **shared dev environment** (`~/dev-environment`, standards repo `templates/dev-environment/`) — database `nexo_id`, standard ports. SSO provider is Passport 13 + `jeremy379/laravel-openid-connect` ([ADR-008](docs/adr/ADR-008-oidc-bridge-correction.md)).

## How to run it

No local PHP/Composer — everything via Docker (like the siblings). Stateful services are shared across projects (`~/dev-environment`): MySQL `:3306` (db `nexo_id`, user/pass `dev`/`dev`), Mailpit SMTP `:1025` / UI `:8025`. This repo's `compose.yaml` runs only the app runtime and reaches them via `host.docker.internal`.

```bash
cd ~/dev-environment && docker compose up -d mysql mailpit   # shared services first
docker run --rm -v "$PWD":/app -w /app composer:latest composer install
export WWWUSER=$(id -u) WWWGROUP=$(id -g)
docker compose up -d                 # app :8100 (APP_PORT in .env)
docker compose exec laravel.test php artisan migrate
npm install && npm run build
# checks (as CI): via the composer container
docker run --rm -v "$PWD":/app -w /app composer:latest sh -c 'vendor/bin/pint --test && vendor/bin/phpstan analyse && vendor/bin/pest'
node scripts/generate-translations.mjs --check   # i18n guardian (needs node)
```

Note: the `composer:latest` container has no node/GD; the i18n guardian Pest test skips there and runs in CI (which has node) plus the standalone `--check` step.

## Production

Live at **https://nexoid.alvarocdev.com** (deployed 2026-07-20 via the `deploy-laravel-hostinger` skill; Hostinger shared + LiteSpeed). Runbook: [DEPLOYMENT.md](DEPLOYMENT.md). OIDC endpoints served (`/.well-known/openid-configuration`, `/oauth/jwks`, authorize/token/userinfo). Passport RSA keys were generated on the server (never in git). Deploy-specific internal identifiers (account, DB name, SSH host) live in `CLAUDE.local.md` (gitignored), not here — this file is public-bound.

**Gate 2 signed off 2026-07-20.** Production email smoke passed (real SMTP verification mail received); attribution footer shows "powered by alvarocdev.com". **Deferred by owner (tracked, not dropped):** verified backups + uptime monitoring — to be set up across all Nexo tools together before real users arrive (Phase 3, when Nexo Short brings the first registrations). Standards require both for production with real users; acceptable to defer only while there are none.

## Project conventions

- This project runs on the `planning-by-stages` skill: [docs/PLAN.md](docs/PLAN.md) is the governing doc — one numbered task at a time, gate per phase with owner sign-off, ADRs in [docs/adr/](docs/adr/), SPEC before code with AC↔test traceability.
- Docs in English (public repo). Communication with Alvaro in Spanish.
- Nexo product conventions apply (see siblings nexo-links/nexo-agenda as reference): zero external requests at runtime, i18n en/es/pt, `NEXO_ATTRIBUTION_*` footer, strict CSP + sync test, Pest/Pint/Larastan + CI.

## Key decisions

- **2026-07-19** — Foundational ADRs 001–005 **accepted by Alvaro at Gate 0** (product model, stack, SSO protocol, tool auth model, migration strategy). See [docs/adr/](docs/adr/). Gate decisions: repo slug `nexo-id`, hosted instance `nexoid.alvarocdev.com`.
- **2026-07-19** — Fact from Gate 0 that reshaped ADR-005: **neither Nexo Links nor Nexo Agenda has registered users yet**, so "migration" is just lazy account linking — no import machinery.
- **2026-07-19** — `nexo-id.md` (root) is the pre-planning evaluation document: treat it as **input, not decisions**. ADR-003/004/005 explicitly supersede its §2–4; its §5 security minimums live on as SCOPE requirements. Before the repo goes public, decide whether it stays (it's Spanish and pre-decision; candidate to drop or move once ADRs are accepted).

## Accumulated context

- **2026-07-21** — **Phase 3 opened** (trigger: Nexo Short is mid-plan; its launch phase needs the client pattern and T4). Decisions with Alvaro: (a) the reusable OIDC client pattern is a **copyable template in the standards repo** (`templates/nexo-sso-client/`), NOT a Composer package — no versioning overhead, consumers copy code + Pest tests, like `templates/dev-environment/` (standards-repo addition approved per PROMPT §4); (b) T4 (verified backups + uptime monitoring, cross-tool) is task 3.1, early and owner-gated — hard precondition before Nexo Short brings real users. SPEC-client.md written (14 ACs: CFG/FLOW/LINK/SESS/DEGRADE); Phase 3 tasks 3.1–3.6 derived in PLAN.md. Cross-project note: Nexo Short's agent must never build the pattern unilaterally — it consumes the template, coordinated here (task 3.4).
- **2026-07-20** — **Standards-sync audit + shared dev environment.** Full audit against the standards system ran clean (report in standards repo `inbox/nexoid/report.md`): suite/CI/AC-traceability/production all verified by execution. Local env migrated to the shared dev environment (`~/dev-environment`, first project to adopt it): `compose.yaml` now runs only the app runtime; MySQL/Mailpit are shared (db `nexo_id`, standard ports); old empty `nexoid_sail-mysql` volume removed. Tests are unaffected (env pinned in `phpunit.xml`: sqlite `:memory:`, mail array). Known gaps carried as tasks, not silent: SEO-base missing on public pages (no meta description/OG/canonical/hreflang/sitemap); pre-public cleanups (genericize `DEPLOYMENT.md` real hosting layout, decide `nexo-id.md`, author email); backups+uptime still deferred to Phase 3 (owner decision).
- **2026-07-20** — **Phase 2 SSO provider built (build ACs green), deploy pending.** Passport 13 + `jeremy379/laravel-openid-connect` (ADR-008 — the ADR-007 bridge was incompatible with Passport 13). Gotchas worth knowing: (a) **`dont-discover: [laravel/passport]`** in composer.json is load-bearing — the bridge's provider extends Passport's and injects the `id_token`; if both auto-register, no id_token is issued; (b) RSA keys (`storage/oauth-*.key`) are gitignored and generated per env (`passport:keys`) — CI generates them before Pest; (c) first-party = owner-less clients (consent-free via `OauthClient::skipsAuthorization`); (d) the verified-email gate on `/oauth/authorize` is a Passport-route middleware (`config/passport.php`); (e) TestResponse in this stack has no `status()` — use `getStatusCode()`; (f) the HTTP test client's session id isn't stable across method changes (already bit us in Phase 1 sessions — SSO tests avoid relying on it). 16 SSO ACs each name-traced; full suite 65+ pass.
- **2026-07-20** — **Phase 1 (standalone identity core) built, signed off at Gate 1.** Registration, email verification (signed gating), login/logout (enumeration-safe, per-credential lockout), password recovery (hashed single-use tokens, change-notification + other-session revocation), profile (display name, current-password-gated change), active-session listing/revocation via the DB session driver, security headers/CSP, i18n en/es/pt. 30 ACs each with a name-traced Pest test (47 pass, 1 node-skip); Pint/Larastan/composer-audit green; `audit-open-source` clean. Gotchas worth knowing: (a) tests use sqlite `:memory:` + `SESSION_DRIVER=array`, so session-revocation tests set `session.driver=database` and the revoke tests drive a **deterministic 40-char session id** (the HTTP test client's session id isn't stable across method changes — a real gotcha, see `SessionManagementTest`); (b) es/pt of framework `validation.*`/`auth.*` messages are English-only for now (backlog, noted in SPEC reconciliation); (c) `email_verified_at` is intentionally **not fillable** (mass-assignment) — verify via `markEmailAsVerified()`.
- **2026-07-19** — **Nexo Events planned its Phase 0** (`/Users/alvarocarrizales/nexoevents`): its ADR-003 adopts this project's ADR-004 model as-is — standalone local organizer auth in its MVP, Nexo ID as optional env-configured SSO added post-MVP via the Phase 3 client pattern. Attendees are email-only in its v1; attendee accounts ("my tickets") arrive with Nexo ID in its v2. No launch coupling in either direction (consistent with PLAN Phase 5 note).
- **2026-07-19** — Phase 0 executed and **Gate 0 signed off**: SCOPE, ADRs 001–005 accepted, PLAN, formalization. Key finding that shaped ADR-003: Nexo Short lives on `nxo.li` (own domain), so the parent-domain-cookie SSO from the evaluation doc breaks on the very first client. Next: Phase 1 (core identity service), which opens with the SPEC and the Passport/OIDC-on-Hostinger spike (task 1.1). Repo initialized, **nothing committed yet** (owner review pending per standards).
