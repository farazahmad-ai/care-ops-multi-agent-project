# ADR-002: Orchestration framework (LangGraph for stateful flows, plain Python and rules for simple ones)

- **Status:** Accepted
- **Date:** 2026-11-10 (updated 2026-11-12 to align with [`docs/project-brief.md`](../project-brief.md) v0.2)
- **Deciders:** Project owner
- **Related:** [`docs/project-brief.md`](../project-brief.md) (Sections 5.1–5.8, `FR-11`, `FR-20`, `NFR-20`), [`docs/architecture.md`](../architecture.md), [`AGENTS.md`](../../AGENTS.md) (Hard Boundary #1)

## Context

Following the six-step solution ladder in [ADR-001](ADR-001-router-first-multi-agent-where-justified.md) and [`docs/project-brief.md`](../project-brief.md) (Section 5), our platform needs an execution model that supports:

- Zero-overhead deterministic execution for exact checks (Step 1) and fixed recipes (Step 2)
- Explicit, inspectable control flow for tool-using agents (Step 4) and multi-agent teams (Step 5)
- Persisted state with durable pause and resume (`NFR-20` — human approvers may review offers or payroll actions hours or days later)
- Mandatory human-in-the-loop approval gates (`FR-11`)
- Full step-by-step auditability (`FR-18`, `NFR-11`) and hard step/cost/timeout budgets (`FR-20`, `NFR-10`)

Options considered: **LangGraph**, **CrewAI**, **AutoGen** (and its successors), and **plain Python**.

## Decision

1. **Rules Engine (no AI, Step 1):** Use deterministic Python modules in `services/agent-api` for exact checks (DBS/right-to-work expiry dates, shift overlaps, leave clashes, timesheet hours and invoice-line arithmetic).
2. **Plain Python State Machines (Step 2):** Use framework-free Python state machines in `services/agent-api` for predictable workflows (for example, leave requests and compliance expiry reminders).
3. **LangGraph (Steps 4 & 5):** Use **LangGraph** (with the PostgreSQL checkpointer) inside `agents/` (`supervisor`, `recruitment`, `hr`, `ops`, `finance`, `auditor`) for stateful single-agent and multi-agent workflows that require cyclical planning, checkpoints and human approval interrupts.
4. **Hard Boundary:** **CrewAI** and **AutoGen** are not used in this repository (`AGENTS.md` Hard Boundary #1).

## Rationale

| Requirement | Rules / Plain Python (Steps 1–2) | LangGraph (Steps 4–5) |
|---|---|---|
| Explicit control flow | Direct Python functions and transition tables | Directed state graph with typed nodes and edges |
| Pause and resume (`NFR-20`) | Database status row | Built-in PostgreSQL checkpointing across restarts |
| Human approval (`FR-11`) | API state transition | First-class `interrupt` and resume gates |
| Bounded runs (`FR-20`) | Deterministic termination | Enforced recursion/step limits, cost caps and timeouts |
| Auditability (`FR-18`) | Structured log + MCP audit row | Every node transition and tool call traced in OpenTelemetry & Langfuse |

CrewAI and AutoGen were evaluated **on paper against these requirements**, not built in this project. Our assessment (to be re-verified against current documentation before it is quoted publicly):

- **CrewAI:** strong for fast, role-based prototyping. Control flow is more emergent, which makes strict approval gates, custom checkpoint resumption and deterministic audit trails harder to enforce.
- **AutoGen:** centred on conversational agent-to-agent interaction. Open-ended conversation loops are harder to bound in token cost and latency (`NFR-7`, `NFR-10`).

## Consequences

- Team members need to learn LangGraph concepts (typed state, nodes, conditional edges, Postgres checkpointers).
- We accept framework coupling inside `agents/`, and mitigate it by keeping business rules, tool schemas, authorization policies and reusable patterns outside the graph logic (in `services/mcp-servers`, OPA, and `templates/agent-starter` per `FR-25`).
- Simple flows stay framework-free, so we never pay framework complexity or LLM cost where plain Python suffices.

## What would change this decision

- Requirements shift to exploratory, open-ended research agents where unconstrained conversational collaboration is the primary goal.
- LangGraph's licensing, stability or maintenance changes materially.
- A competing framework offers equal state persistence and interrupt control with less boilerplate.

## Follow-up

An optional half-day spike (one small workflow in CrewAI, with notes) in a **separate repository** would turn the paper evaluation into first-hand evidence.
