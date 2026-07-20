# ADR-005 — Existing-user migration: lazy account linking by verified email (no import needed)

- **Date:** 2026-07-19
- **Status:** Accepted (Gate 0 sign-off, 2026-07-19)

## Context

The evaluation document (§4) treated migration of existing Nexo Links / Nexo Agenda users as "the only work with real complexity": unification by email, credential conflicts, FK re-mapping with a bridge table. **Fact established at Gate 0: neither tool has registered users yet.** Both are in production but their registries are empty, so there is nothing to migrate today. ADR-004 additionally keeps local users tables in every tool (standalone mode), so central identity is a *link*, never a table replacement.

## Decision

1. **No import/migration machinery is built.** The evaluation doc's §4 pipeline (inventory, unification, credential-conflict rules, bridge tables) is dropped from scope.
2. Each tool adds a nullable `nexo_id_sub` column to its local users table and a standard SSO client (ADR-003/004). On first "Sign in with Nexo ID", the tool links to an existing local account by **verified email match**, or creates a local account if none exists. Local PKs/FKs never change.
3. Alvaro's instances switch to **SSO-only as soon as each tool's integration ships** — with no existing users there is no grace period, user communication, or parallel-login window to manage.
4. **Safety valve:** if users do register in a tool before its integration ships, the linking flow in (2) already absorbs them (their local account links on first SSO login; recovery flow covers a forgotten-password mismatch). Re-check user counts when Phase 4 opens; only if counts are meaningful does a pre-import (one Nexo ID account per email, oldest account's bcrypt hash imported as-is) get reconsidered.

## Alternatives considered

- **Pre-import + linking** (this ADR's original proposal) — unnecessary with zero users; kept as the documented fallback in (4).
- **FK re-mapping / drop local users tables** (evaluation doc §4) — rejected: invasive production-data surgery that ADR-004 made pointless.

## Consequences

- "Migration" collapses into plain integration work: Phase 4 of the PLAN is per-tool SSO integration, not data migration.
- The sooner Phase 4 ships, the smaller the safety-valve window — mild scheduling pressure, no hard constraint.
- Supersedes nexo-id.md §4 entirely.
