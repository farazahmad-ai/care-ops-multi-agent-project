"""Asynchronous PostgreSQL Audit Log Writer (FR-18, NFR-11)."""

from __future__ import annotations

import asyncio
import json
import logging
import os
from typing import Any
from uuid import UUID

import asyncpg

from careops_agent_api.audit.models import AuditLogEntry

logger = logging.getLogger(__name__)

DEFAULT_POSTGRES_DSN = "postgresql://careops:careops_dev_secret@localhost:5432/careops_platform"


class AuditLogWriter:
    """Writes and queries immutable audit log records in PostgreSQL."""

    def __init__(self, dsn: str | None = None) -> None:
        self.dsn = dsn or os.environ.get("POSTGRES_URL", DEFAULT_POSTGRES_DSN)
        self._pool: asyncpg.Pool[Any] | None = None
        self._loop: asyncio.AbstractEventLoop | None = None

    async def get_pool(self) -> asyncpg.Pool[Any]:
        """Lazily creates and returns the asyncpg connection pool tied to the running event loop."""
        current_loop = asyncio.get_running_loop()
        if self._pool is None or self._loop != current_loop:
            if self._pool is not None:
                try:
                    await self._pool.close()
                except Exception:
                    pass
            self._pool = await asyncpg.create_pool(
                self.dsn,
                min_size=1,
                max_size=10,
                command_timeout=10,
            )
            self._loop = current_loop
        return self._pool

    async def write_entry(self, entry: AuditLogEntry) -> UUID:
        """Persists an immutable audit log entry into PostgreSQL."""
        pool = await self.get_pool()
        query = """
            INSERT INTO audit_logs (
                id,
                trace_id,
                timestamp,
                user_id,
                user_role,
                agent_id,
                tool_name,
                input_payload,
                output_payload,
                policy_decision,
                cost_gbp
            ) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11)
            RETURNING id;
        """
        async with pool.acquire() as conn:
            row = await conn.fetchrow(
                query,
                entry.id,
                entry.trace_id,
                entry.timestamp,
                entry.user_id,
                entry.user_role,
                entry.agent_id,
                entry.tool_name,
                json.dumps(entry.input_payload),
                json.dumps(entry.output_payload),
                entry.policy_decision,
                float(entry.cost_gbp),
            )
            if row is None:
                raise RuntimeError("Failed to insert audit log record")
            logger.info(
                "Audit log recorded",
                extra={
                    "trace_id": entry.trace_id,
                    "user_id": entry.user_id,
                    "tool_name": entry.tool_name,
                    "policy_decision": entry.policy_decision,
                },
            )
            return row["id"]  # type: ignore[no-any-return]

    async def get_by_trace_id(self, trace_id: str) -> list[AuditLogEntry]:
        """Retrieves audit entries associated with a given trace_id within 5m (NFR-11)."""
        pool = await self.get_pool()
        query = """
            SELECT
                id,
                trace_id,
                timestamp,
                user_id,
                user_role,
                agent_id,
                tool_name,
                input_payload,
                output_payload,
                policy_decision,
                cost_gbp
            FROM audit_logs
            WHERE trace_id = $1
            ORDER BY timestamp ASC;
        """
        async with pool.acquire() as conn:
            rows = await conn.fetch(query, trace_id)
            entries: list[AuditLogEntry] = []
            for r in rows:
                entries.append(
                    AuditLogEntry(
                        id=r["id"],
                        trace_id=r["trace_id"],
                        timestamp=r["timestamp"],
                        user_id=r["user_id"],
                        user_role=r["user_role"],
                        agent_id=r["agent_id"],
                        tool_name=r["tool_name"],
                        input_payload=(
                            json.loads(r["input_payload"])
                            if isinstance(r["input_payload"], str)
                            else r["input_payload"]
                        ),
                        output_payload=(
                            json.loads(r["output_payload"])
                            if isinstance(r["output_payload"], str)
                            else r["output_payload"]
                        ),
                        policy_decision=r["policy_decision"],
                        cost_gbp=r["cost_gbp"],
                    )
                )
            return entries

    async def close(self) -> None:
        """Closes the connection pool."""
        if self._pool is not None:
            await self._pool.close()
            self._pool = None
            self._loop = None
