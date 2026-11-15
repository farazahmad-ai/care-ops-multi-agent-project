"""Tests for get_carer_dbs_status MCP Tool (Task 1.5, Tool DoD)."""

import uuid

import pytest
from careops_mcp.server import (
    CareOpsMcpServer,
    McpPolicyDeniedError,
    McpRecordNotFoundError,
)


@pytest.mark.asyncio
async def test_mcp_get_carer_dbs_status_authorized() -> None:
    """Authorized staff user should receive DBS status and record ALLOW in audit log."""
    server = CareOpsMcpServer()
    trace_id = f"test-mcp-{uuid.uuid4().hex[:12]}"

    try:
        result = await server.get_carer_dbs_status(
            carer_id=1,
            user_id="staff.user@example.test",
            user_role="staff",
            trace_id=trace_id,
        )

        assert result.carer_id == 1
        assert result.status in {"valid", "expiring_soon", "expired"}
        assert result.days_remaining is not None

        # Verify audit log in PostgreSQL
        audit_records = await server.audit_writer.get_by_trace_id(trace_id)
        assert len(audit_records) == 1
        assert audit_records[0].policy_decision == "ALLOW"
        assert audit_records[0].tool_name == "get_carer_dbs_status"
        assert audit_records[0].user_id == "staff.user@example.test"
    finally:
        await server.close()


@pytest.mark.asyncio
async def test_mcp_get_carer_dbs_status_denied() -> None:
    """Unauthorized role should be denied by OPA and record DENY in audit log."""
    server = CareOpsMcpServer()
    trace_id = f"test-mcp-deny-{uuid.uuid4().hex[:12]}"

    try:
        with pytest.raises(McpPolicyDeniedError) as exc_info:
            await server.get_carer_dbs_status(
                carer_id=1,
                user_id="unauthorized.user@example.test",
                user_role="guest",
                trace_id=trace_id,
            )

        assert "not authorized" in str(exc_info.value)

        # Verify DENY audit log in PostgreSQL
        audit_records = await server.audit_writer.get_by_trace_id(trace_id)
        assert len(audit_records) == 1
        assert audit_records[0].policy_decision == "DENY"
        assert audit_records[0].user_id == "unauthorized.user@example.test"
    finally:
        await server.close()


@pytest.mark.asyncio
async def test_mcp_get_carer_dbs_status_not_found() -> None:
    """Non-existent carer should raise McpRecordNotFoundError."""
    server = CareOpsMcpServer()
    trace_id = f"test-mcp-404-{uuid.uuid4().hex[:12]}"

    try:
        with pytest.raises(McpRecordNotFoundError):
            await server.get_carer_dbs_status(
                carer_id=999999,
                user_id="staff.user@example.test",
                user_role="staff",
                trace_id=trace_id,
            )
    finally:
        await server.close()
