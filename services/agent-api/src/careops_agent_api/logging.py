"""Structured JSON logging with request-scoped trace IDs via contextvars."""

import json
import logging
import sys
from contextvars import ContextVar, Token
from datetime import UTC, datetime
from typing import Any
from uuid import uuid4

_trace_id_var: ContextVar[str | None] = ContextVar("trace_id", default=None)


def new_trace_id() -> str:
    """Generate a new 32-character hexadecimal trace ID."""
    return uuid4().hex


def get_trace_id() -> str | None:
    """Return the active trace ID for the current execution context, if any."""
    return _trace_id_var.get()


def set_trace_id(trace_id: str) -> Token[str | None]:
    """Bind a trace ID to the current execution context and return its reset token."""
    return _trace_id_var.set(trace_id)


def reset_trace_id(token: Token[str | None]) -> None:
    """Restore the previous trace ID using the token returned by set_trace_id."""
    _trace_id_var.reset(token)


class JsonLogFormatter(logging.Formatter):
    """Formats log records as single-line JSON including the active trace ID."""

    def format(self, record: logging.LogRecord) -> str:
        """Serialize a LogRecord into a structured JSON string."""
        payload: dict[str, Any] = {
            "timestamp": datetime.fromtimestamp(record.created, tz=UTC).isoformat(),
            "level": record.levelname,
            "logger": record.name,
            "message": record.getMessage(),
            "trace_id": get_trace_id(),
        }
        if record.exc_info:
            payload["exception"] = self.formatException(record.exc_info)
        return json.dumps(payload, default=str)


def configure_logging(level: str = "INFO") -> None:
    """Configure root logging to emit structured JSON lines to stdout."""
    handler = logging.StreamHandler(sys.stdout)
    handler.setFormatter(JsonLogFormatter())

    root_logger = logging.getLogger()
    root_logger.handlers.clear()
    root_logger.addHandler(handler)
    root_logger.setLevel(level.upper())
