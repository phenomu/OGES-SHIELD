"""Tool registry — loader + validator tools.json (tracker Phase 3).

Schema check ketat: tool dengan format salah → ToolRegistryError,
orchestrator menolak memuat (bukan silent-skip).
"""
from __future__ import annotations

import json
import threading
from pathlib import Path
from typing import Any

from config import settings


class ToolRegistryError(Exception):
    """tools.json invalid — orchestrator harus menolak memuat."""


_VALID_CATEGORIES = {"recon", "vulnerability", "exploitation", "persistence", "cleanup", "report"}
_VALID_RISK = {"safe", "intrusive", "destructive"}
_VALID_PARSERS = {"jsonlines", "json", "text"}
_REQUIRED_FIELDS = {"id", "name", "category", "valid_phases", "risk_level",
                    "command_template", "input_schema", "timeout_seconds", "enabled"}
_SCHEMA_TYPES = {"string", "number", "boolean", "integer", "object", "array"}


def _validate_tool(tool: dict, index: int) -> None:
    where = f"tools[{index}]"

    if not isinstance(tool, dict):
        raise ToolRegistryError(f"{where}: harus object")
    missing = _REQUIRED_FIELDS - set(tool.keys())
    if missing:
        raise ToolRegistryError(f"{where}: field wajib hilang: {sorted(missing)}")
    if not isinstance(tool["id"], str) or not tool["id"]:
        raise ToolRegistryError(f"{where}: id wajib string non-kosong")
    if not isinstance(tool["valid_phases"], list) or not tool["valid_phases"]:
        raise ToolRegistryError(f"{where}: valid_phases wajib list non-kosong")
    if tool["category"] not in _VALID_CATEGORIES:
        raise ToolRegistryError(f"{where}: category '{tool['category']}' tidak dikenal")
    if tool["risk_level"] not in _VALID_RISK:
        raise ToolRegistryError(f"{where}: risk_level '{tool['risk_level']}' tidak dikenal")
    if tool["output_parser"] not in _VALID_PARSERS:
        raise ToolRegistryError(f"{where}: output_parser '{tool['output_parser']}' tidak dikenal")
    if not isinstance(tool["timeout_seconds"], int) or tool["timeout_seconds"] <= 0:
        raise ToolRegistryError(f"{where}: timeout_seconds wajib integer > 0")

    schema = tool["input_schema"]
    if not isinstance(schema, dict) or schema.get("type") != "object":
        raise ToolRegistryError(f"{where}: input_schema.type wajib 'object'")
    props = schema.get("properties")
    if not isinstance(props, dict) or not props:
        raise ToolRegistryError(f"{where}: input_schema.properties wajib non-kosong")
    for pname, pdef in props.items():
        if not isinstance(pdef, dict) or pdef.get("type") not in _SCHEMA_TYPES:
            raise ToolRegistryError(f"{where}: parameter '{pname}' tanpa type yang valid")
        if "enum" in pdef and not isinstance(pdef["enum"], list):
            raise ToolRegistryError(f"{where}: enum parameter '{pname}' wajib list")

    # Guardrail 9.2: destructives wajib requires_confirmation
    if tool["risk_level"] == "destructive" and not tool.get("requires_confirmation"):
        raise ToolRegistryError(
            f"{where}: risk_level 'destructive' wajib requires_confirmation: true"
        )

    # template parameter wajib cocok dengan input_schema
    import re
    placeholders = set(re.findall(r"\{(\w+)\}", tool["command_template"]))
    declared = set(props.keys())
    unknown = placeholders - declared
    if unknown:
        raise ToolRegistryError(
            f"{where}: template pakai parameter {sorted(unknown)} yang tidak ada di input_schema"
        )


class ToolRegistry:
    """Thread-safe registry + reload dari file."""

    def __init__(self) -> None:
        self._tools: dict[str, dict] = {}
        self._lock = threading.RLock()

    def load(self) -> int:
        """Load + validate tools.json. Return jumlah tool terdaftar."""
        path: Path = settings.TOOLS_JSON
        raw = path.read_text(encoding="utf-8")
        try:
            data = json.loads(raw)
        except json.JSONDecodeError as e:
            raise ToolRegistryError(f"tools.json bukan JSON valid: {e}") from e

        tools = data.get("tools")
        if not isinstance(tools, list) or not tools:
            raise ToolRegistryError("tools.json: key 'tools' wajib list non-kosong")

        validated: dict[str, dict] = {}
        seen_ids: set[str] = set()
        for i, tool in enumerate(tools):
            _validate_tool(tool, i)
            tid = tool["id"]
            if tid in seen_ids:
                raise ToolRegistryError(f"tools[{i}]: id duplikat '{tid}'")
            seen_ids.add(tid)
            validated[tid] = tool

        with self._lock:
            self._tools = validated
        return len(validated)

    def all(self) -> list[dict]:
        with self._lock:
            return list(self._tools.values())

    def get(self, tool_id: str) -> dict | None:
        with self._lock:
            return self._tools.get(tool_id)

    def for_phase(self, phase: str, enabled_only: bool = True) -> list[dict]:
        with self._lock:
            tools = [t for t in self._tools.values() if phase in t["valid_phases"]]
        if enabled_only:
            tools = [t for t in tools if t.get("enabled", False)]
        return tools

    def count(self) -> int:
        with self._lock:
            return len(self._tools)


registry = ToolRegistry()
