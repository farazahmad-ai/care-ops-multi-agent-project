"""API boundary schemas and DTO models (Task 1.7)."""

from __future__ import annotations

from datetime import UTC, datetime
from typing import Any, Literal
from uuid import UUID, uuid4

from pydantic import BaseModel, ConfigDict, Field


class RequestPayload(BaseModel):
    """User request submission schema from Screen 1."""

    model_config = ConfigDict(extra="forbid")

    intent: Literal["check_carer_dbs", "get_carer_compliance"] = Field(
        description="The operational intent of the request"
    )
    parameters: dict[str, Any] = Field(
        description="Parameters required for intent execution (e.g. {'carer_id': 1})"
    )


class RequestResponse(BaseModel):
    """Structured response returned to frontend."""

    model_config = ConfigDict(extra="ignore")

    request_id: UUID = Field(default_factory=uuid4)
    trace_id: str
    status: Literal["done", "error", "rejected"]
    result: dict[str, Any] = Field(default_factory=dict)
    timestamp: datetime = Field(default_factory=lambda: datetime.now(UTC))


class HealthCheckResponse(BaseModel):
    """Health check status for platform infrastructure."""

    model_config = ConfigDict(extra="ignore")

    status: Literal["healthy", "degraded", "unhealthy"]
    services: dict[str, str]
    timestamp: datetime = Field(default_factory=lambda: datetime.now(UTC))
