# ADR-006: Platform Docker Compose Location and Sandbox Network Topology

- **Status:** Accepted
- **Date:** 2026-11-14
- **Deciders:** Project owner
- **Related:** [`docs/project-brief.md`](../project-brief.md) (Sections 2, 6, 18.1; `NFR-1`, `NFR-17`), [`docs/architecture.md`](../architecture.md) (Section 6, 7), [`docs/roadmap.md`](../roadmap.md) (Task 0.4, 1.1; Open Item `O4`), [ADR-004](ADR-004-simulated-existing-company.md), [`AGENTS.md`](../../AGENTS.md)

## Context

The CareOps platform runs alongside the handed-over legacy company backend in `company-sandbox/`.
- `company-sandbox/docker-compose.yml` runs three containers: `careops_legacy_db` (MySQL 8.0 on host port `3307`), `careops_legacy_app` (PHP-FPM 8.4), and `careops_legacy_web` (nginx on host port `8080`) connected to a custom Docker bridge network named `careops_sandbox_network`.
- Open item `O4` in [`docs/roadmap.md`](../roadmap.md) asked: Where should the platform's Compose file live (`infra/` vs repository root), and how should platform containers communicate with the legacy system without exposing MySQL port `3307` to agents (`NFR-1`)?

## Decision

1. **Root `docker-compose.yml` for Platform Orchestration (`NFR-17`):**
   - Place the primary platform `docker-compose.yml` at the repository root. This ensures standard commands like `docker compose up -d` or `make up` work immediately without navigating into subfolders or requiring custom `-f` arguments.
   - Store service-specific Dockerfiles, configuration files, and seed assets under `infra/` (`infra/postgres/`, `infra/opa/`, `infra/keycloak/`).

2. **Network Topology & External Bridge Attachment (`NFR-1`):**
   - The platform `docker-compose.yml` defines its own internal network (`careops_platform_network`) for platform-only services (PostgreSQL + pgvector, Redis, Keycloak, OPA, FastAPI `agent-api`, LiteLLM).
   - The platform Compose file references the existing sandbox network as an **external network**:
     ```yaml
     networks:
       careops_platform_network:
         driver: bridge
       careops_sandbox_network:
         external: true
     ```
   - **Only the MCP server container (`services/mcp-servers`) is attached to both networks.** The MCP server reaches the legacy HTTP API at `http://careops_legacy_web:80/api` using internal DNS.
   - The legacy MySQL container (`careops_legacy_db`) port `3307` is mapped only to host `127.0.0.1` for developer inspection and is **never attached to the platform network**. AI agents, FastAPI, and LiteLLM reside solely on `careops_platform_network` and physically cannot resolve or route packets to MySQL.

## Consequences

### Positive
- **Strict Compliance with `NFR-1` & `ADR-004`:** Even if an agent is prompt-injected or compromised, it lacks network connectivity to the legacy MySQL port.
- **Service Discovery:** MCP servers communicate directly over Docker's internal DNS (`careops_legacy_web`) without going out through the developer's host network or relying on brittle `localhost` port forwarding.
- **Independent Lifecycles:** The legacy system in `company-sandbox/` can be started, stopped, or seeded independently of the platform stack.
- **Convenient Developer Experience:** Root `docker compose up -d` manages the platform services cleanly.

### Negative / Trade-offs
- The legacy sandbox network (`careops_sandbox_network`) must be created first by running `docker compose -f company-sandbox/docker-compose.yml up -d` before starting platform containers that reference it as external. Platform startup documentation and `Makefile` will encode this dependency.

## Alternatives Considered

- **Single monolithic Compose file:** Merging `company-sandbox` containers into the platform `docker-compose.yml` blurs the boundary of the pre-existing company handover and violates the principle of treating `company-sandbox` as a pre-existing external enterprise asset.
- **Host networking (`host.docker.internal`):** Brittle across different operating systems (Windows WSL2 vs Linux), exposes ports unnecessarily, and prevents Docker DNS service discovery.
