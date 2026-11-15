# CareOps Agent Platform Makefile
.PHONY: lint test eval up down build-frontend verify-contracts

lint:
	uv run ruff check services agents scripts
	uv run black --check services agents scripts
	uv run mypy --strict services/agent-api/src services/agent-api/tests services/mcp-servers/src services/mcp-servers/tests scripts

test:
	uv run pytest
	docker exec careops_platform_opa /opa test /policies -v

verify-contracts:
	uv run python scripts/verify_legacy_contracts.py

build-frontend:
	cd frontend && npm run build

# Stub: Phase 3 will add Promptfoo / DeepEval golden evaluation suite
eval:
	@echo "[STUB] make eval is not implemented yet — scheduled for Phase 3 (Task 3.12)."

up:
	docker compose up -d

down:
	docker compose down
