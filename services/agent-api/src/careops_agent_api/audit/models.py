"""Audit logging domain models (FR-18, NFR-11)."""

from __future__ import annotations

from datetime import UTC, datetime
from decimal import Decimal
from typing import Any, Literal
from uuid import UUID, uuid4

from pydantic import BaseModel, ConfigDict, Field


class AuditLogEntry(BaseModel):
    """Immutable audit record schema matching Postgres audit_logs table."""

    model_config = ConfigDict(extra="ignore")

    id: UUID = Field(default_factory=uuid4)
    trace_id: str
    timestamp: datetime = Field(default_factory=lambda: datetime.now(UTC))
    user_id: str
    user_role: str
    agent_id: str | None = None
    tool_name: str
    input_payload: dict[str, Any] = Field(default_factory=dict)
    output_payload: dict[str, Any] = Field(default_factory=dict)
    policy_decision: Literal["ALLOW", "DENY"]
    cost_gbp: Decimal = Field(default=Decimal("0.0000"))
