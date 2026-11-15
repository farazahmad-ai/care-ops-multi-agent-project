"""Unit tests for structured JSON logging and trace ID propagation."""

import json
import logging

from careops_agent_api.logging import (
    JsonLogFormatter,
    get_trace_id,
    new_trace_id,
    reset_trace_id,
    set_trace_id,
)


def test_json_formatter_includes_active_trace_id() -> None:
    """Emitted JSON log records must include the active context trace ID."""
    formatter = JsonLogFormatter()
    logger = logging.getLogger("careops.test")
    record = logger.makeRecord(
        name="careops.test",
        level=logging.INFO,
        fn=__file__,
        lno=1,
        msg="checked carer compliance",
        args=(),
        exc_info=None,
    )

    token = set_trace_id("trace-abc-123")
    try:
        rendered = formatter.format(record)
    finally:
        reset_trace_id(token)

    parsed = json.loads(rendered)
    assert parsed["level"] == "INFO"
    assert parsed["logger"] == "careops.test"
    assert parsed["message"] == "checked carer compliance"
    assert parsed["trace_id"] == "trace-abc-123"
    assert "timestamp" in parsed


def test_reset_trace_id_restores_previous_context() -> None:
    """Resetting the trace token must restore the prior trace ID (or None)."""
    assert get_trace_id() is None
    generated = new_trace_id()
    assert len(generated) == 32

    token = set_trace_id(generated)
    assert get_trace_id() == generated
    reset_trace_id(token)
    assert get_trace_id() is None
