# ADR-005: Adoption of `uv` for Python Package and Environment Management

- **Status:** Accepted
- **Date:** 2026-11-14
- **Deciders:** Project owner
- **Related:** [`docs/project-brief.md`](../project-brief.md) (Section 13), [`docs/roadmap.md`](../roadmap.md) (Tasks 0.2, 0.4, 0.6; Open Items `O1`, `O2`), [`AGENTS.md`](../../AGENTS.md)

## Context

The CareOps platform backend is built in Python 3.12 (`AGENTS.md`, `project-brief.md` Section 13) using FastAPI, Pydantic v2, and LangGraph. During Phase 0 environment setup:
1. **Python Version Gap (`O1`):** The host developer machine had system Python `3.13.5` installed, whereas `AGENTS.md` and third-party AI orchestration packages strictly require Python `3.12`.
2. **Tooling Approval (`O2`):** `uv` was proposed to manage Python versions, virtual environments, and dependencies, but was not in the initial approved stack list in `AGENTS.md`. Under `AGENTS.md` rule 7 ("Never add a dependency without stating why and recording it"), tooling additions must be recorded in an ADR.
3. Traditional Python package tooling (`pip`, `pip-tools`, `virtualenv`, `poetry`) is notably slow when installing large dependencies and does not natively manage hermetic CPython interpreter downloads.

## Decision

Adopt **`uv`** (by Astral) as the standard Python package, dependency, and environment manager for the CareOps Agent Platform.

1. **Hermetic Python 3.12:** Use `uv` to manage the CPython `3.12.x` runtime via `.python-version` pinned to `3.12`. Developers and CI pipelines run against identical Python 3.12 binaries regardless of host system Python version.
2. **Deterministic Locking:** Use `pyproject.toml` (PEP 518/621) for project manifests and dependency constraints, and commit `uv.lock` to guarantee 100% bit-for-bit reproducible environments.
3. **Quality Toolchain Integration:** Run all development tools (`ruff`, `black`, `mypy --strict`, `pytest`) through `uv run` to ensure they execute inside the managed `.venv/` without activating environments manually.

## Consequences

### Positive
- **Performance:** `uv` is written in Rust and resolves dependencies 10–100x faster than `pip` using parallel async I/O, a PubGrub SAT solver, and content-addressed caching.
- **Hermetic Runtime:** Solves `O1` without needing system-wide Python uninstalls or separate tools like `pyenv`.
- **Reproducibility:** `uv.lock` eliminates "works on my machine" differences across developer laptops and future CI pipelines.
- **Unified Interface:** Replaces `pip`, `virtualenv`, `poetry`, and `pip-tools` with a single binary.

### Negative / Trade-offs
- On Windows systems where the workspace is on a different physical drive from the global `uv` cache (e.g. workspace on `D:\` and user cache on `C:\Users\...`), NTFS cannot create cross-volume hardlinks. `uv` automatically detects this and falls back to fast file copies into `.venv/`.
- Team members must install the standalone `uv` binary on their workstations.

## Alternatives Considered

- **Standard `venv` + `pip`:** Bundled with Python, but very slow, lacks automatic lockfile generation, and cannot install/switch Python versions automatically.
- **Poetry:** Feature-rich, but slower dependency resolution, heavy dependency footprint, and occasional lockfile churn.
- **System Python 3.13 without isolation:** Violates `AGENTS.md` and introduces compatibility breaks with AI libraries (e.g. Torch / LangChain ecosystem) that lag behind the newest Python releases.
