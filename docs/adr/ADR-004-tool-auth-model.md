# ADR-004 — Tool auth model: tools keep standalone auth; Nexo ID is an optional, env-configured SSO provider

- **Date:** 2026-07-19
- **Status:** Accepted (Gate 0 sign-off, 2026-07-19)

## Context

Every Nexo tool is open source and self-hostable. If a third party self-hosts a single tool (say, Nexo Links), how do they solve auth? Forcing them to also deploy Nexo ID doubles their operational cost for one app. This decision shapes the design of **all** tools, crosses into starter-master (future TS tools use Better Auth), and determines how invasive the migration is. The evaluation document assumed tools would *delete* their own users tables and depend entirely on the central service — written before the self-hosting angle was considered.

## Decision

1. **Every Nexo tool keeps (or ships with) standalone local auth** — exactly what Nexo Links and Nexo Agenda have today. A single-tool self-host works out of the box with zero extra services.
2. **Nexo ID integration is an optional, instance-level configuration** (env, e.g. `NEXO_SSO_ISSUER`, `NEXO_SSO_CLIENT_ID`, …). When configured, the tool offers "Sign in with Nexo ID" via the standard flow (ADR-003).
3. Each instance chooses its **auth mode**: local only (default for self-hosts), SSO only, or both during transitions. **Alvaro's hosted instances run SSO-only** once migrated — that's what makes "one account, every tool" true on the official instances.
4. Because the protocol is standard (ADR-003), a self-hoster can point a tool at **any OIDC-compatible provider** (their own Nexo ID, Keycloak, etc.) — Nexo ID is the recommended sibling, not a hard dependency.
5. **Nexo Short launches SSO-only against Alvaro's Nexo ID** on the hosted instance (registration barrier is the point), while still shipping the standalone mode for self-hosters.
6. For future TS tools (starter-master lineage): Better Auth's generic OAuth/OIDC provider support covers this same contract — no Nexo-specific client code needed.

## Alternatives considered

- **Nexo ID as hard dependency; tools drop their users tables** (evaluation doc §3) — rejected: kills the single-tool self-host story, makes migration invasive (FK re-mapping across production data), and turns Nexo ID into a mandatory SPOF for every instance in the world, not just Alvaro's.
- **Standalone-only tools, no SSO (status quo)** — rejected: that's the problem this project solves.
- **Auth mode decided at build time (separate distributions)** — rejected: env-based runtime config is the established Nexo pattern (`NEXO_ATTRIBUTION_*`) and keeps one artifact.

## Consequences

- Tools maintain two auth paths (local + SSO). Mitigated: local auth already exists and stays untouched; SSO is additive (one OIDC client + account linking), and the pattern is built once in Phase 3 and reused.
- Migration becomes account *linking*, not table replacement — see ADR-005.
- Each tool's SPEC (when integrating) must define behavior for auth-mode combinations and for Nexo ID being unreachable (graceful degradation principle).
- Supersedes the "each tool deletes its users table" design in nexo-id.md §3.
