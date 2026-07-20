# Nexo ID

> Entry point for any AI/agent working on this project. It follows Alvaro's standards system (repo `alvaro`, alvarocdev.com). Keep this file updated: persist here the important context that comes up during work sessions.
> This repo will be public: no secrets, credentials, or sensitive infrastructure details here.

## What this project is

Central identity service (SSO) for the Nexo ecosystem (Nexo Links, Nexo Agenda, Nexo Short, Nexo Events): one account, every tool. Open source, multi-instance, self-hostable, like its siblings. **Current state: Phase 1 (standalone identity core) complete, pending Gate 1 owner sign-off.** Start at [docs/PLAN.md](docs/PLAN.md); Phase 1 spec is [SPEC.md](SPEC.md); map is [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Stack

Laravel 13 + MySQL (ADR-002). Blade + Tailwind v4 (Vite), no Alpine. Pest 4 / Pint / Larastan level 6. Local dev via Sail (mysql + mailpit). SSO provider (Passport + `ronvanderheijden/openid-connect`, [ADR-007](docs/adr/ADR-007-oauth-oidc-library.md)) is Phase 2 — not a dependency yet.

## How to run it

No local PHP/Composer — everything via Docker (like the siblings):

```bash
docker run --rm -v "$PWD":/app -w /app composer:latest composer install
export WWWUSER=$(id -u) WWWGROUP=$(id -g)
docker compose up -d                 # app :8100, mysql :3309, mailpit :8027
docker compose exec laravel.test php artisan migrate
npm install && npm run build
# checks (as CI): via the composer container
docker run --rm -v "$PWD":/app -w /app composer:latest sh -c 'vendor/bin/pint --test && vendor/bin/phpstan analyse && vendor/bin/pest'
node scripts/generate-translations.mjs --check   # i18n guardian (needs node)
```

Note: the `composer:latest` container has no node/GD; the i18n guardian Pest test skips there and runs in CI (which has node) plus the standalone `--check` step.

## Production

Not deployed. Planned: `nexoid.alvarocdev.com` (decided at Gate 0), via the `deploy-laravel-hostinger` skill, in Phase 2.

## Project conventions

- This project runs on the `planning-by-stages` skill: [docs/PLAN.md](docs/PLAN.md) is the governing doc — one numbered task at a time, gate per phase with owner sign-off, ADRs in [docs/adr/](docs/adr/), SPEC before code with AC↔test traceability.
- Docs in English (public repo). Communication with Alvaro in Spanish.
- Nexo product conventions apply (see siblings nexo-links/nexo-agenda as reference): zero external requests at runtime, i18n en/es/pt, `NEXO_ATTRIBUTION_*` footer, strict CSP + sync test, Pest/Pint/Larastan + CI.

## Key decisions

- **2026-07-19** — Foundational ADRs 001–005 **accepted by Alvaro at Gate 0** (product model, stack, SSO protocol, tool auth model, migration strategy). See [docs/adr/](docs/adr/). Gate decisions: repo slug `nexo-id`, hosted instance `nexoid.alvarocdev.com`.
- **2026-07-19** — Fact from Gate 0 that reshaped ADR-005: **neither Nexo Links nor Nexo Agenda has registered users yet**, so "migration" is just lazy account linking — no import machinery.
- **2026-07-19** — `nexo-id.md` (root) is the pre-planning evaluation document: treat it as **input, not decisions**. ADR-003/004/005 explicitly supersede its §2–4; its §5 security minimums live on as SCOPE requirements. Before the repo goes public, decide whether it stays (it's Spanish and pre-decision; candidate to drop or move once ADRs are accepted).

## Accumulated context

- **2026-07-20** — **Phase 1 (standalone identity core) built, pending Gate 1 sign-off.** Registration, email verification (signed gating), login/logout (enumeration-safe, per-credential lockout), password recovery (hashed single-use tokens, change-notification + other-session revocation), profile (display name, current-password-gated change), active-session listing/revocation via the DB session driver, security headers/CSP, i18n en/es/pt. 30 ACs each with a name-traced Pest test (47 pass, 1 node-skip); Pint/Larastan/composer-audit green; `audit-open-source` clean. Gotchas worth knowing: (a) tests use sqlite `:memory:` + `SESSION_DRIVER=array`, so session-revocation tests set `session.driver=database` and the revoke tests drive a **deterministic 40-char session id** (the HTTP test client's session id isn't stable across method changes — a real gotcha, see `SessionManagementTest`); (b) es/pt of framework `validation.*`/`auth.*` messages are English-only for now (backlog, noted in SPEC reconciliation); (c) `email_verified_at` is intentionally **not fillable** (mass-assignment) — verify via `markEmailAsVerified()`.
- **2026-07-19** — **Nexo Events planned its Phase 0** (`/Users/alvarocarrizales/nexoevents`): its ADR-003 adopts this project's ADR-004 model as-is — standalone local organizer auth in its MVP, Nexo ID as optional env-configured SSO added post-MVP via the Phase 3 client pattern. Attendees are email-only in its v1; attendee accounts ("my tickets") arrive with Nexo ID in its v2. No launch coupling in either direction (consistent with PLAN Phase 5 note).
- **2026-07-19** — Phase 0 executed and **Gate 0 signed off**: SCOPE, ADRs 001–005 accepted, PLAN, formalization. Key finding that shaped ADR-003: Nexo Short lives on `nxo.li` (own domain), so the parent-domain-cookie SSO from the evaluation doc breaks on the very first client. Next: Phase 1 (core identity service), which opens with the SPEC and the Passport/OIDC-on-Hostinger spike (task 1.1). Repo initialized, **nothing committed yet** (owner review pending per standards).
