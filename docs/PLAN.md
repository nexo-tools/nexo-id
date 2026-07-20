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

Key work: `SPEC.md` with numbered ACs (registration, login, logout, recovery, email verification, sessions with revocation, profile; the SCOPE security minimums as ACs); **task 1.1 is the spike** — validate the OAuth2/OIDC server library (Laravel Passport first candidate) against Hostinger shared constraints (`proc_open`/`exec` disabled, LiteSpeed) and reconcile SPEC + ADR-003 with findings; project scaffolding (Laravel + Sail, Pest/Pint/Larastan, CI per nexo-agenda reference); i18n generator, brand assets, attribution env, SecurityHeaders + CSP sync test (canonical pieces from CATALOG.md).

**Gate 1:** all ACs green with name-traced tests; `grep` AC↔test pass; deliberate-violation checks (rate limit actually blocks, expired/reused recovery token rejected); security audit exercised (not theoretical); ARCHITECTURE matches reality; owner sign-off.

## Phase 2 — SSO provider + production deploy

**Objective:** the OAuth 2.0 + PKCE / OIDC-style flow of ADR-003 live at `nexoid.alvarocdev.com`.

Key work: SPEC for the provider layer (client registration, authorize/token/userinfo endpoints, silent SSO, central logout, consent-free first-party clients); a demo/reference client proving the flow end-to-end from a different domain; integration guide for tool developers; deploy via `deploy-laravel-hostinger` playbook; production baseline per standards — verified backups (restore tested once), uptime monitoring, cron, SMTP.

**Gate 2:** full flow exercised from an external domain (silent SSO included); token/PKCE negative tests; deployed and verified in production (HTTP + real flow); backups restored once for real; owner sign-off.

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
