# ADR-004: Integrating with the Pre-Existing CareOps Company System (`company-sandbox/`)

- **Status:** Accepted
- **Date:** 2026-11-11 (updated 2026-11-12 to align with [`docs/project-brief.md`](../project-brief.md) v0.2)
- **Deciders:** Project owner
- **Related:** [`docs/project-brief.md`](../project-brief.md) (Sections 2, 3, 5, `FR-4` to `FR-8`, `Q-3` to `Q-5`), [`docs/architecture.md`](../architecture.md), [`company-sandbox/README.md`](../../company-sandbox/README.md)

## Context

The CareOps Agent Platform automates routine recruitment, HR, operations and finance administration for a fictional UK care-staffing business (**CareOps Ltd**). In any realistic enterprise deployment:

1. Core business systems (HR, rostering/operations, timesheets, invoicing/ERP, candidate CRM) **already exist** before AI agents arrive.
2. As detailed in [`docs/project-brief.md`](../project-brief.md) (Section 2), the handed-over legacy system has realistic strengths and realistic gaps:
   - **What it protects:** 12 write-side business rules (**R1–R12**) block unsafe mutations at the API boundary (such as assigning a carer with expired DBS/right-to-work, double-booking shifts, booking during approved leave, role mismatches between `carer` and `senior_carer`, duplicate timesheet approvals, or hiring without `offered` status and valid checks).
   - **What it lacks:** It uses a single shared bearer `SERVICE_TOKEN` with no per-user or per-agent identity; it does not proactively scan existing records for historical anomalies (expired checks on future shifts, overlapping bookings, duplicate timesheets, excessive hours, past open shifts, invoice-total mismatches, duplicate client records); its 21 written SOPs contain real-world ambiguities (two annual-leave policy versions `annual-leave-policy-v1.md` and `annual-leave-policy.md`, DBS re-check cycle ambiguity, and a manual paper Form HR-7B step); and it cannot interpret unstructured CV text or provide leadership dashboards.
3. Under UK GDPR and [ADR-003](ADR-003-permissions-outside-the-model.md), agents must never receive direct database credentials or hold the legacy `SERVICE_TOKEN`.

The pre-existing CareOps company system is handed over running in `company-sandbox/`.

## Decision

1. **Pre-Existing Legacy Boundary (`company-sandbox/`):**
   - Runs **Laravel 12.72** (PHP 8.4-FPM) + **MySQL 8.0** + **nginx 1.27** inside its own Docker Compose stack (`http://localhost:8080/api`, MySQL on host port `3307`, network `careops_sandbox_network`).
   - Exposes ~30 REST endpoints ([`company-sandbox/laravel-app/openapi.yaml`](../../company-sandbox/laravel-app/openapi.yaml)) protected by a single bearer `SERVICE_TOKEN` (fails closed if unset) and enforces write-side business rules **R1–R12** inside `app/Services/`.
   - Contains 13 relational tables ([`company-sandbox/database/schema.md`](../../company-sandbox/database/schema.md)) populated with 100% synthetic `en_GB` data and **21 company SOP markdown documents** (`company-sandbox/sops/`) across `recruitment/`, `hr/`, `operations/`, `finance/`, and `general/`.
   - Reference evaluation manifests and sandbox notes live outside the platform codebase and SOP corpus in `local/` (gitignored) so evaluations remain blind.

2. **Strict Architectural Separation (`NFR-1`):**
   - The AI platform **never touches the legacy MySQL database directly** (port `3307` is never exposed to agents or MCP servers).
   - MCP servers (`services/mcp-servers`) are the sole holders of `SERVICE_TOKEN` and wrap the legacy REST API without re-implementing write rules R1–R12.
   - Read-side anomaly detection (Step 1 rules engine in `services/agent-api`, `FR-5`, `FR-6`) queries the legacy API via MCP read tools to surface the historical data issues that R1–R12 do not retroactively flag.
   - Agents read company policies exclusively through semantic retrieval (RAG in PostgreSQL + `pgvector`, `FR-8`) over `company-sandbox/sops/` with file and section citations, while Step 0 process redesign decisions (`Q-3`, `Q-4`, `Q-5` in [`docs/project-brief.md`](../project-brief.md) Section 19) resolve SOP conflicts with the project owner.

## Consequences

### Positive
- **High Fidelity:** The legacy system behaves like an authentic external PHP/MySQL enterprise back end with real HTTP latency, validation and domain error codes (`R1–R12`).
- **Clear Value Demonstration:** Shows both how deterministic rules (Step 1) catch legacy data anomalies and how RAG/agents (Steps 3–5) handle unstructured CVs, policy questions and cross-department coordination.
- **Blind Evaluations:** Keeping answer-key manifests in `local/` prevents RAG leakage during evaluation runs (`FR-24`).
- **UK GDPR Safe (`NFR-13`):** All records use synthetic names, `@example.test` emails and UK drama-range phone numbers (`07700 900000`–`07700 900999`).

### Negative / Trade-offs
- Platform local development runs alongside the `company-sandbox/` Compose stack (`careops_sandbox_network` / `http://localhost:8080`).
- Because the legacy API authenticates via a single `SERVICE_TOKEN`, fine-grained `User ∩ Agent` authorization, idempotency keys (`FR-19`) and immutable audit logging (`FR-18`) must be strictly enforced upstream at the MCP + OPA layer ([ADR-003](ADR-003-permissions-outside-the-model.md)).

## Alternatives Considered

- **In-memory Python mocks inside test suites:** Zero infrastructure cost, but fails to test HTTP boundaries, serialization, legacy rule responses (`R1–R12`), or cross-stack integration.
- **Building a fresh Python mock inside `services/`:** Duplicates the platform language stack and hides realistic legacy integration constraints.
