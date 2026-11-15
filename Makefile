# CareOps Agent Platform Makefile (Phase 0)
.PHONY: lint test eval up down

lint:
	uv run ruff check services agents
	uv run black --check services agents
	uv run mypy --strict services/agent-api/src services/agent-api/tests

test:
	uv run pytest

verify-contracts:
	uv run python scripts/verify_legacy_contracts.py

# Stub: Phase 3 will add Promptfoo / DeepEval golden evaluation suite
eval:
	@echo "[STUB] make eval is not implemented yet — scheduled for Phase 3 (Task 3.12)."

up:
	docker compose up -d

down:
	docker compose down

