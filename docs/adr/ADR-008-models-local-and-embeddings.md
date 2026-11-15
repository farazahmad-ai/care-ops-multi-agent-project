# ADR-008: AI Model Gateway, Local Runtime, and Embedding Model Selection

- **Status:** Accepted
- **Date:** 2026-11-14
- **Deciders:** Project owner
- **Related:** [`docs/project-brief.md`](../project-brief.md) (Sections 5.3, 5.7, 10, 14.4, 19; `FR-8`, `FR-22`, `NFR-10`, `NFR-13`, `NFR-19`; Open Items `O5`, `O6`, `O7`, `Q-1`, `Q-2`), [`docs/architecture.md`](../architecture.md) (Section 3, 5), [`docs/roadmap.md`](../roadmap.md) (Tasks 0.4, 3.1, 3.2, 3.3, 3.6), [ADR-001](ADR-001-router-first-multi-agent-where-justified.md)

## Context

The CareOps platform utilizes LLMs for policy retrieval-augmented generation (RAG over 21 SOPs, `FR-8`), candidate CV screening (`FR-9`), and multi-agent cross-department coordination (`FR-14`, `FR-15`).
To meet our architectural principles and compliance obligations:
1. **Never Call Model SDKs Directly (`AGENTS.md`, `FR-22`, `NFR-19`):** Agent code must never import `openai`, `anthropic`, or provider-specific SDKs directly. A unified gateway must enforce timeouts, spend caps, fallbacks, and cost tracking.
2. **Local Model Runtime for Sensitive Data & Offline Development (`O6`, `Q-1`, `NFR-13`):** Under UK GDPR principles, special-category data (unredacted fit notes, DBS disclosures) and offline developer environments require a private, local LLM runtime.
3. **Embedding Model for SOP RAG (`O7`, `Q-1`, `FR-8`):** The 21 SOP markdown files in `company-sandbox/sops/` must be chunked and indexed in PostgreSQL using `pgvector` for semantic search.
4. **Budget & Cost Caps (`NFR-10`, `FR-20`):** Production tasks must stay under £0.05 average per task, with a hard per-run cap of £0.50 and monthly budget alerts.

## Decision

1. **Gateway: LiteLLM Proxy (`FR-22`, `NFR-19`):**
   - Deploy **LiteLLM Proxy** as a dedicated container in the platform stack.
   - All agents route LLM requests through `http://litellm:4000/v1` using standard OpenAI-compatible completions and embeddings endpoints.
   - LiteLLM enforces per-model aliases, spend caps, fallback models (e.g. Claude 3.5 Sonnet -> GPT-4o -> Local Ollama), and outputs cost/token metadata to OpenTelemetry and Langfuse (`NFR-12`).

2. **Local Model Runtime: Ollama with `llama3.1:8b-instruct` (`O6`, `Q-1`, `NFR-13`):**
   - Adopt **Ollama** as the approved local inference engine.
   - Standard local model: **`llama3.1:8b-instruct`** (4-bit quantized / Q4_K_M, requiring ~5 GB VRAM/RAM).
   - Serves two purposes:
     - Acts as the zero-cost offline engine during local development, integration tests, and unit evals without consuming cloud API credits.
     - Serves as the compliant processing route for sensitive or unmasked personal data (`NFR-13`).
   - Configured in LiteLLM as model alias `local-llm` pointing to `http://host.docker.internal:11434` (or containerized Ollama).

3. **Embedding Model: Dual-Route Architecture (`O7`, `Q-1`, `FR-8`):**
   - **Local / Self-Hosted Embedding:** **`BAAI/bge-small-en-v1.5`** (384 dimensions). It is lightweight (~130 MB), executes fast on CPU/local hardware, and ranks near the top of the MTEB retrieval benchmark for its parameter class.
   - **Cloud Embedding Alternative:** **`text-embedding-3-small`** (1536 dimensions) via LiteLLM proxy for production environments where managed cloud embeddings are preferred.
   - **Vector Database:** PostgreSQL with the `pgvector` extension, utilizing `HNSW` (Hierarchical Navigable Small World) indexing with cosine distance (`vector_cosine_ops`) for sub-second retrieval (`NFR-7`).

4. **External Production LLMs (`O5`, `Q-2`):**
   - External providers: Anthropic (Claude 3.5 Sonnet for supervisor reasoning and complex screening) and OpenAI (GPT-4o-mini for fast routing and summarization).
   - API keys are injected solely into LiteLLM via environment variables; keys are never exposed to agent code or committed to repositories.

## Consequences

### Positive
- **Complete Vendor Neutrality:** Swapping an underlying LLM provider requires changing a single line in LiteLLM's `config.yaml`, with zero code changes in `agents/` or `services/agent-api`.
- **UK GDPR Compliance (`NFR-13`):** Sensitive records can be routed exclusively to the local Ollama instance without leaving the local network boundary.
- **Strict Budget Guardrails (`NFR-10`):** LiteLLM acts as a hard circuit breaker if spending exceeds £0.50 on a run or monthly budget thresholds are reached.
- **Fast, High-Precision RAG:** `bge-small-en-v1.5` provides high-accuracy semantic retrieval across our 21 SOP documents with negligible RAM consumption.

### Negative / Trade-offs
- Local inference via Ollama requires developers to run Ollama on their host machine or dedicate 6–8 GB of RAM to Docker for local container execution.
- Vector dimension schemas in `pgvector` must match the chosen embedding model (384 dimensions for `bge-small-en-v1.5` vs 1536 for `text-embedding-3-small`). The platform configuration specifies this via `EMBEDDING_DIMENSIONS`.

## Alternatives Considered

- **Direct Provider SDKs (`anthropic`, `openai`):** Strictly forbidden by `AGENTS.md`. Bypasses centralized budgeting, prevents fallback failover, and locks code to proprietary interfaces.
- **vLLM as Local Runtime:** High throughput for production GPU clusters, but unnecessarily complex and resource-heavy for single-developer laptops compared to Ollama.
- **In-Memory Chroma / FAISS:** Does not scale with persistent ACID guarantees. PostgreSQL with `pgvector` allows relational tables (audit logs, approvals) and vector embeddings to live safely in the same managed database.
