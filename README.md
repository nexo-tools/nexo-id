<div align="center">

<img src="resources/brand/isotype.svg" width="88" alt="Nexo ID isotype">

# Nexo ID

**One account, every Nexo tool — the open-source identity service (SSO) of the Nexo ecosystem.**

</div>

---

Status: Phases 1 & 2 complete — the SSO provider (OAuth 2.0 + PKCE / OIDC: authorize, token, id_token, userinfo, discovery, JWKS, silent SSO, central logout) is **live at https://nexoid.alvarocdev.com**. Phase 3 (reusable OIDC client pattern + Nexo Short as the first consumer + ops hardening) is nearly complete: the client template ships and Nexo Short runs SSO-only against this provider in production.

- Scope: [docs/SCOPE.md](docs/SCOPE.md)
- Specs: [SPEC.md](SPEC.md) (core) · [SPEC-sso.md](SPEC-sso.md) (SSO provider) · [SPEC-client.md](SPEC-client.md) (reusable client pattern)
- Plan & gates: [docs/PLAN.md](docs/PLAN.md)
- Architecture: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)
- Tool integration: [docs/INTEGRATION.md](docs/INTEGRATION.md)
- Decisions: [docs/adr/](docs/adr/)

## Nexo ecosystem

Nexo is a family of open-source, self-hostable tools that share one visual identity
([nexo-brand](https://github.com/nexo-tools)), one optional account
([Nexo ID](https://github.com/nexo-tools/nexo-id) SSO) and one set of engineering
standards. Every tool runs **fully standalone** — the ecosystem is opt-in.

| Tool | What it is | Repo |
| --- | --- | --- |
| **Nexo Tools** | Ecosystem hub — discover the tools and hop between them with one account | [nexo-tools](https://github.com/nexo-tools/nexo-tools) |
| **Nexo Links** | Link-in-bio you host yourself (Linktree alternative) | [nexo-links](https://github.com/nexo-tools/nexo-links) |
| **Nexo Agenda** | Bookings for service businesses (AgendaPro / Fresha / Booksy alternative) | [nexo-agenda](https://github.com/nexo-tools/nexo-agenda) |
| **Nexo Short** | Self-hosted URL shortener | [nexo-short](https://github.com/nexo-tools/nexo-short) |
| **Nexo Events** | Event tickets and passes | [nexo-events](https://github.com/nexo-tools/nexo-events) |
| **Nexo ID** | One account for every tool — OAuth 2.0 / OIDC SSO | — you are here |

New to Nexo? Start at **[nexotools.alvarocdev.com](https://nexotools.alvarocdev.com)**.
Built by **[alvarocdev.com](https://alvarocdev.com)** — the tech behind Nexo.
