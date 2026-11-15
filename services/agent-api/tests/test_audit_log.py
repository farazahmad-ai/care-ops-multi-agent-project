"""Tests for Audit Log Writer and Models (Task 1.6, FR-18, NFR-11)."""

import os
import uuid
from decimal import Decimal

import pytest
from careops_agent_api.audit import AuditLogEntry, AuditLogWriter


def test_audit_log_entry_model() -> None:
    """Verifies that AuditLogEntry model enforces expected types and defaults."""
    entry = AuditLogEntry(
        trace_id="trace-test-123",
        user_id="sarah@example.test",
        user_role="staff",
        tool_name="get_carer_dbs_status",
        input_payload={"carer_id": 12},
        output_payload={"dbs_status": "valid"},
        policy_decision="ALLOW",
        cost_gbp=Decimal("0.0000"),
    )
    assert entry.trace_id == "trace-test-123"
    assert entry.user_id == "sarah@example.test"
    assert entry.policy_decision == "ALLOW"
    assert entry.id is not None
    assert entry.timestamp is not None


@pytest.mark.asyncio
async def test_audit_log_writer_persist_and_retrieve() -> None:
    """Verifies persisting both ALLOW and DENY entries in PostgreSQL and retrieving by trace_id."""
    dsn = os.environ.get(
        "POSTGRES_URL", "postgresql://careops:careops_dev_secret@localhost:5432/careops_platform"
    )
    writer = AuditLogWriter(dsn=dsn)
    trace_id = f"test-trace-{uuid.uuid4().hex[:12]}"

    try:
        # 1. Write an ALLOW entry
        allow_entry = AuditLogEntry(
            trace_id=trace_id,
            user_id="staff.user@example.test",
            user_role="staff",
            tool_name="get_carer_dbs_status",
            input_payload={"carer_id": 1},
            output_payload={"dbs_status": "valid", "days_remaining": 269},
            policy_decision="ALLOW",
            cost_gbp=Decimal("0.0000"),
        )
        record_id = await writer.write_entry(allow_entry)
        assert record_id == allow_entry.id

        # 2. Write a DENY entry
        deny_entry = AuditLogEntry(
            trace_id=trace_id,
            user_id="unauthorized.user@example.test",
            user_role="none",
            tool_name="get_carer_dbs_status",
            input_payload={"carer_id": 1},
            output_payload={"error": "Access denied by OPA policy"},
            policy_decision="DENY",
            cost_gbp=Decimal("0.0000"),
        )
        await writer.write_entry(deny_entry)

        # 3. Retrieve by trace_id
        records = await writer.get_by_trace_id(trace_id)
        assert len(records) == 2
        assert records[0].policy_decision == "ALLOW"
        assert records[0].input_payload == {"carer_id": 1}
        assert records[1].policy_decision == "DENY"
        assert records[1].user_id == "unauthorized.user@example.test"
    finally:
        await writer.close()
