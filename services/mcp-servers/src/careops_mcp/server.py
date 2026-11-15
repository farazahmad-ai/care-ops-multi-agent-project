"""CareOps Safe Gateway MCP Tool Server (Task 1.5, ADR-003, ADR-004, NFR-1).

Provides the only audited doorway to legacy systems, holding CAREOPS_SERVICE_TOKEN.
"""

from __future__ import annotations

import logging
import os
from decimal import Decimal
from typing import Any

import httpx
from careops_agent_api.audit import AuditLogEntry, AuditLogWriter

from careops_mcp.models import CarerDbsInput, CarerDbsResult

logger = logging.getLogger(__name__)

DEFAULT_LEGACY_URL = "http://localhost:8080"
DEFAULT_SERVICE_TOKEN = "careops_legacy_sandbox_token_2026"
DEFAULT_OPA_URL = "http://localhost:8181"


class McpPolicyDeniedError(Exception):
    """Raised when an OPA policy check fails for an MCP tool call."""


class McpRecordNotFoundError(Exception):
    """Raised when the requested carer record is not found in legacy systems."""


class CareOpsMcpServer:
    """Safe Gateway MCP server wrapping business systems with OPA and audit logging."""

    def __init__(
        self,
        legacy_url: str | None = None,
        service_token: str | None = None,
        opa_url: str | None = None,
        audit_writer: AuditLogWriter | None = None,
    ) -> None:
        self.legacy_url = (
            legacy_url or os.environ.get("CAREOPS_LEGACY_URL", DEFAULT_LEGACY_URL)
        ).rstrip("/")
        self.service_token = service_token or os.environ.get(
            "CAREOPS_SERVICE_TOKEN", DEFAULT_SERVICE_TOKEN
        )
        self.opa_url = (opa_url or os.environ.get("OPA_URL", DEFAULT_OPA_URL)).rstrip("/")
        self.audit_writer = audit_writer or AuditLogWriter()
        self._http_client: httpx.AsyncClient | None = None

    async def get_http_client(self) -> httpx.AsyncClient:
        """Returns or lazily creates a shared async HTTP client tied to the current loop."""
        import asyncio

        current_loop = asyncio.get_running_loop()
        if (
            self._http_client is None
            or self._http_client.is_closed
            or getattr(self, "_loop", None) != current_loop
        ):
            if self._http_client is not None and not self._http_client.is_closed:
                try:
                    await self._http_client.aclose()
                except Exception:
                    pass
            self._http_client = httpx.AsyncClient(timeout=10.0)
            self._loop = current_loop
        return self._http_client

    async def check_opa_policy(
        self, user_role: str, tool_name: str, action: str = "execute"
    ) -> bool:
        """Queries OPA policy engine to evaluate User Permissions ∩ Agent Permissions (ADR-003)."""
        client = await self.get_http_client()
        query_url = f"{self.opa_url}/v1/data/careops/compliance/allow"
        payload = {
            "input": {
                "user_role": user_role,
                "tool": tool_name,
                "action": action,
            }
        }
        try:
            resp = await client.post(query_url, json=payload)
            if resp.status_code == 200:
                result = resp.json().get("result")
                return bool(result is True)
            logger.warning("OPA policy returned non-200 status", extra={"status": resp.status_code})
            return False
        except Exception as exc:
            logger.error("Failed to query OPA policy server", exc_info=exc)
            # Fail closed (deny) per NFR-2
            return False

    async def get_carer_dbs_status(
        self,
        carer_id: int,
        user_id: str,
        user_role: str,
        trace_id: str,
        agent_id: str | None = None,
    ) -> CarerDbsResult:
        """Looks up a carer's DBS compliance status through audited Safe Gateway.

        Meets Tool DoD (project-brief.md Section 18.2):
        1. Validates input schema via Pydantic (CarerDbsInput).
        2. Evaluates OPA authorization policy.
        3. Persists immutable audit record (ALLOW or DENY) to Postgres.
        4. Calls legacy company API using secured SERVICE_TOKEN.
        5. Returns structured, validated CarerDbsResult.
        """
        # 1. Validate Input Schema
        validated_input = CarerDbsInput(carer_id=carer_id)
        tool_name = "get_carer_dbs_status"

        # 2. OPA Policy Authorization Check (FR-2, ADR-003)
        allowed = await self.check_opa_policy(
            user_role=user_role, tool_name=tool_name, action="execute"
        )

        if not allowed:
            # Audit the denial (FR-18, NFR-11)
            deny_entry = AuditLogEntry(
                trace_id=trace_id,
                user_id=user_id,
                user_role=user_role,
                agent_id=agent_id,
                tool_name=tool_name,
                input_payload=validated_input.model_dump(),
                output_payload={"error": "Access denied by OPA policy"},
                policy_decision="DENY",
                cost_gbp=Decimal("0.0000"),
            )
            await self.audit_writer.write_entry(deny_entry)
            raise McpPolicyDeniedError(
                f"Role '{user_role}' is not authorized to execute tool '{tool_name}'"
            )

        # 3. Call Legacy CareOps API using SERVICE_TOKEN (NFR-1)
        client = await self.get_http_client()
        api_url = f"{self.legacy_url}/api/carers/{validated_input.carer_id}/compliance"
        headers = {
            "Authorization": f"Bearer {self.service_token}",
            "Accept": "application/json",
        }

        resp = await client.get(api_url, headers=headers)
        if resp.status_code == 404:
            raise McpRecordNotFoundError(f"Carer with ID {validated_input.carer_id} not found")

        resp.raise_for_status()
        checks: list[dict[str, Any]] = resp.json()

        # 4. Filter for DBS check item
        dbs_items = [c for c in checks if c.get("type") == "dbs"]
        if not dbs_items:
            result = CarerDbsResult(
                carer_id=validated_input.carer_id,
                status="missing",
                issued_on=None,
                expires_on=None,
                days_remaining=None,
            )
        else:
            dbs = dbs_items[0]
            result = CarerDbsResult(
                carer_id=validated_input.carer_id,
                status=dbs.get("computed_status", "valid"),
                issued_on=dbs.get("issued_on"),
                expires_on=dbs.get("expires_on"),
                days_remaining=dbs.get("days_remaining"),
            )

        # 5. Persist ALLOW Audit Log Record (FR-18, NFR-11)
        allow_entry = AuditLogEntry(
            trace_id=trace_id,
            user_id=user_id,
            user_role=user_role,
            agent_id=agent_id,
            tool_name=tool_name,
            input_payload=validated_input.model_dump(),
            output_payload=result.model_dump(),
            policy_decision="ALLOW",
            cost_gbp=Decimal("0.0000"),
        )
        await self.audit_writer.write_entry(allow_entry)

        return result

    async def close(self) -> None:
        """Closes the HTTP client and audit writer connections."""
        if self._http_client is not None:
            await self._http_client.aclose()
            self._http_client = None
        await self.audit_writer.close()
