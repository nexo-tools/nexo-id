# ADR-001 — Product model: Nexo ID is a standalone, open source, multi-instance Nexo tool

- **Date:** 2026-07-19
- **Status:** Accepted (Gate 0 sign-off, 2026-07-19)

## Context

The Nexo ecosystem (Nexo Links, Nexo Agenda, upcoming Nexo Short and Nexo Events) needs unified identity. All existing tools are open source (MIT), publicly usable on Alvaro's hosted instances, and self-hostable by third parties. The evaluation document (nexo-id.md, 2026-07-19) validated the idea of a central identity service. The standing rule is that Nexo ID follows the same open source model unless an ADR decides otherwise — this ADR makes that explicit.

## Decision

1. Nexo ID is built as **its own product and repo**, sibling to nexo-links/nexo-agenda: open source, **MIT license**, documentation in **English**, public repo after the `audit-open-source` skill passes.
2. It inherits the Nexo product conventions proven in production: zero external requests at runtime, i18n en/es/pt, instance-configurable attribution (`NEXO_ATTRIBUTION_*`), strict CSP with sync test, CI with dependency audit.
3. **Scope boundary: identity/authentication only.** Tools keep their own data and authorization, referencing users by their Nexo ID subject. No tool-specific data in Nexo ID.
4. Alvaro's hosted instance runs at **`nexoid.alvarocdev.com`** (decided at Gate 0: consistent with `nexolinks.` / `nexoagenda.alvarocdev.com`). GitHub repo slug: **`nexo-id`** (sibling pattern).

## Alternatives considered

- **Closed-source / hosted-only identity service** — rejected: breaks the ecosystem promise; third parties self-hosting a tool would depend on Alvaro's infrastructure for login.
- **Auth as a shared library copied into each tool (no central service)** — rejected: no SSO, no single account; duplicates the users problem this project exists to solve.

## Consequences

- The repo must be publishable: no secrets or internal infra details in tracked files; audit before going public.
- Being open source and public makes the service a target: security minimums are MVP acceptance criteria (see SCOPE).
- Self-hosting story must be first-class (see ADR-004).
