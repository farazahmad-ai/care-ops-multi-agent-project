# ADR-001: Simplest solution ladder — router and workflows by default, multi-agent only where justified

- **Status:** Accepted
- **Date:** 2026-11-10 (updated 2026-11-12 to align with [`docs/project-brief.md`](../project-brief.md) v0.2)
- **Deciders:** Project owner
- **Related:** [`docs/project-brief.md`](../project-brief.md) (Section 5, `FR-5` to `FR-15`), [`docs/architecture.md`](../architecture.md), [`docs/roadmap.md`](../roadmap.md) (Phases 2, 3, 5)

## Context

The CareOps Agent Platform automates recruitment, HR, operations and finance tasks on top of the existing CareOps legacy system (`company-sandbox/`). Across these four departments:

- Some problems are caused by the **manual process or untidy documents** themselves (for example, a paper clearance form HR-7B, two conflicting annual-leave policy files, or duplicate client records).
- Most daily checks are **exact and deterministic** (for example, DBS or right-to-work expiry dates, shift overlaps, leave clashes, duplicate timesheets, hours exceeding shift length, or invoice totals not matching their lines).
- Many workflows follow a **fixed recipe** (for example, leave request submission, 30-day compliance expiry reminders, weekly payroll cut-off nudges).
- Some requests need **one LLM call grounded in company SOPs (RAG)** or **one tool-using specialist agent** (for example, answering a policy question with citations, or screening a batch of CVs).
- Only a subset of requests are **open-ended and cross multiple departments** (for example: *"A carer resigned: find shift cover, check payroll impact, and notify the client"*).

## Decision

Follow a strict **six-step complexity ladder** (see [`docs/project-brief.md`](../project-brief.md) Section 5) with a **router-first entry point**:

| Step | Pattern | Uses AI? | When used at CareOps |
|---|---|---|---|
| **0. Process redesign** | Simplify the business process or clean up SOPs/data first | No | Replace paper Form HR-7B with a digital checklist (`Q-5`); reconcile the two annual-leave SOP versions (`Q-4`); merge duplicate client records |
| **1. Rules engine** | Deterministic Python code (`RulesEngine`) | No | Exact date, arithmetic, overlap and lookup checks (`FR-5`, `FR-6`: DBS/RTW expiry, shift overlaps, leave clashes, duplicate/excessive timesheets, invoice-line totals) |
| **2. Fixed workflows** | Plain Python state machines (`PyStateMachine`) | No | Predictable multi-step flows (`FR-7`, `FR-16`, `FR-17`: leave workflow, 30-day expiry warning, Sunday timesheet reminder) |
| **3. Single LLM call + RAG** | LiteLLM + `pgvector` retrieval with citations and Pydantic structured output | Yes (1 call) | Policy Q&A over the 21 SOPs (`FR-8`), single-pass structured extraction |
| **4. Single specialist agent** | One bounded LangGraph worker with its own identity, minimal MCP tools and budget | Yes (1 agent) | Domain-specific multi-step tasks such as Recruitment CV screening (`FR-9`) |
| **5. Multi-agent team** | LangGraph **Supervisor (Coordinator)** + specialist workers (`recruitment`, `hr`, `ops`, `finance`) + independent **Auditor (Checker)** | Yes (multi-agent) | Open-ended, cross-domain tasks (`FR-13` to `FR-15`) |

### Routing & Confidence Progression

1. **Router v0 (Phase 2, `FR-7`):** Start with a deterministic, rule-based router mapping explicit intents to Step 1 rules and Step 2 workflows.
2. **Confidence Router (Phase 3, `FR-10`):** Add a structured LLM intent classifier **only where** rule routing is insufficient. Every classification outputs a confidence score; if confidence is below the configured threshold, the request routes to the **Human Approval & Escalation Queue** (`Screen 2`) instead of guessing.
3. **Justification Rule ( Step 5):** Multi-agent orchestration is used **only** where at least one of these holds:
   - The task is open-ended and spans multiple departments.
   - Separation of duties is required (the **Auditor / Checker** agent in `agents/auditor` reviews actions of other agents and can never review its own actions).
   - Least privilege requires isolated toolsets and Keycloak service identities per domain (`User ∩ Agent` in OPA, [ADR-003](ADR-003-permissions-outside-the-model.md)).
   - Independent evaluation, budget or human ownership per agent is required.

## Consequences

**Positive**
- Zero LLM cost and sub-2-second latency (`NFR-7`) for the highest-volume checks (timesheets and compliance account for 144 of the 380 estimated monthly hours saved in [`docs/project-brief.md`](../project-brief.md) Section 14.3).
- Easier to test, explain and audit.
- A prompt injection in one worker agent cannot reach tools outside its narrow scope.

**Negative**
- Multiple execution paths (`RulesEngine`, `PyStateMachine`, `LangGraph`) to maintain behind the router.
- Router mistakes could send requests to the wrong path. Mitigation: confidence thresholds routing uncertain cases to humans (`FR-10`).
- Multi-agent runs consume more tokens and latency (`NFR-7`: p95 < 120s; `NFR-10`: £0.50 per-run cap). Mitigation: OpenTelemetry + Langfuse tracing on every run and hard step/cost/timeout budgets (`FR-20`).

## Validation plan

In Phase 5 (Task 5.5 of [`docs/roadmap.md`](../roadmap.md)), build the cross-domain resignation/cover scenario both ways (single agent plus router vs. supervisor plus specialist workers plus auditor). Compare accuracy, token cost, latency and prompt-injection resilience in `evals/`. Record the measured comparison in `docs/adr/ADR-001-results.md` and revisit this ADR if the data disagrees.

## Alternatives considered

- **Automate without process redesign:** perpetuates paper bottlenecks (Form HR-7B) and contradictory SOPs.
- **Single agent for everything:** simplest wiring, but causes tool sprawl, a wide prompt-injection blast radius and no separation of duties.
- **Multi-agent for everything:** flexible, but expensive, slow and impossible to justify for date arithmetic or fixed checklists.
