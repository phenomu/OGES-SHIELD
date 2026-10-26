"""OGES-SHIELD orchestrator — entrypoint FastAPI.

Service internal (localhost only): dieksekusi oleh PHP backend,
JANGAN pernah diekspos langsung ke browser (guardrail: AI_ROUTER_API_KEY
hanya hidup di .env orchestrator, tidak pernah dikirim ke client).
"""
from __future__ import annotations

import uuid
from typing import Any

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel

import db
from config import settings
from tools.registry import ToolRegistryError, registry
from tools.runner import (ScopeViolation, ToolValidationError, audit,
                           run_tool, write_event)

app = FastAPI(title="OGES-SHIELD Orchestrator", version="0.2.0")


@app.on_event("startup")
def _load_registry() -> None:
    try:
        n = registry.load()
        print(f"[registry] {n} tool dimuat dari {settings.TOOLS_JSON}")
    except ToolRegistryError as e:
        # registry invalid → service tetap hidup utk /health, tapi tools kosong.
        print(f"[registry] GAGAL: {e}")
    for d in (settings.REPORTS_DIR, settings.EVIDENCE_DIR):
        d.mkdir(parents=True, exist_ok=True)


# ------------------------------------------------------------------ models --

class RunCreate(BaseModel):
    template_slug: str
    target_id: int
    created_by: int


class ToolExecute(BaseModel):
    run_id: str
    tool_id: str
    params: dict[str, Any]
    phase: str = "manual"
    user_id: int | None = None


# ------------------------------------------------------------------ health --

@app.get("/health")
def health() -> dict:
    return {
        "status": "ok",
        "service": "oges-shield-orchestrator",
        "version": "0.2.0",
        "db_configured": bool(settings.DB_NAME),
        "ai_configured": bool(settings.AI_ROUTER_API_KEY),
        "tools_loaded": registry.count(),
    }


# -------------------------------------------------------------------- runs --

@app.post("/runs")
def create_run(body: RunCreate) -> dict:
    """Buat run baru (status pending). PHP yang memanggil ini dari UI Set Workflow."""
    template = db.query_one(
        "SELECT id, slug, phases FROM workflow_templates WHERE slug = %s",
        (body.template_slug,),
    )
    if template is None:
        raise HTTPException(404, f"template '{body.template_slug}' tidak ditemukan")

    target = db.query_one(
        "SELECT id, value, in_scope FROM targets WHERE id = %s", (body.target_id,)
    )
    if target is None:
        raise HTTPException(404, f"target id={body.target_id} tidak ditemukan")
    if not target["in_scope"]:
        raise HTTPException(422, "target tidak in-scope — run ditolak (guardrail 9.1)")

    run_id = str(uuid.uuid4())
    db.execute(
        "INSERT INTO workflow_runs (id, template_id, target_id, created_by, status, phases) "
        "VALUES (%s, %s, %s, %s, 'pending', %s)",
        (run_id, template["id"], body.target_id, body.created_by, template["phases"]),
    )
    audit(body.created_by, "run_created", {
        "run_id": run_id, "template": body.template_slug, "target": target["value"],
    }, run_id=run_id)
    write_event(run_id, "scope_validation", "run_started", {
        "template": template["slug"], "target": target["value"],
    })
    return {"run_id": run_id, "status": "pending"}


@app.get("/runs/{run_id}/events")
def run_events(run_id: str, after: int = 0, limit: int = 100) -> dict:
    """Ambil event setelah id tertentu (incremental — Phase 3 polling PHP)."""
    limit = max(1, min(limit, 500))
    rows = db.query(
        "SELECT id, phase, type, payload, created_at FROM run_events "
        "WHERE run_id = %s AND id > %s ORDER BY id ASC LIMIT %s",
        (run_id, after, limit),
    )
    for r in rows:
        if isinstance(r.get("payload"), str):
            try:
                r["payload"] = __import__("json").loads(r["payload"])
            except Exception:
                pass
    return {"run_id": run_id, "last_event_id": rows[-1]["id"] if rows else after,
            "count": len(rows), "events": rows}


# ------------------------------------------------------- internal (PHP only) --

@app.post("/internal/execute-tool")
def execute_tool(body: ToolExecute) -> dict:
    """Eksekusi 1 tool spesifik untuk run (dipakai panel engine manual + Phase 4 loop)."""
    try:
        result = run_tool(
            run_id=body.run_id, tool_id=body.tool_id, params=body.params,
            user_id=body.user_id, phase=body.phase,
        )
    except ScopeViolation as e:
        raise HTTPException(422, str(e))
    except (ToolValidationError, ToolRegistryError) as e:
        raise HTTPException(400, str(e))
    return result


@app.post("/internal/reload-tools")
def reload_tools() -> dict:
    """Reload tools.json (dipicu admin UI Phase 4)."""
    try:
        n = registry.load()
        audit(None, "tools_reloaded", {"count": n})
        return {"ok": True, "tools_loaded": n}
    except ToolRegistryError as e:
        raise HTTPException(422, f"tools.json invalid: {e}")


@app.get("/internal/tools")
def list_tools(phase: str | None = None) -> dict:
    """Daftar tool (untuk sinkronisasi mirror PHP)."""
    tools = registry.for_phase(phase) if phase else registry.all()
    return {"count": len(tools), "tools": [
        {"id": t["id"], "name": t["name"], "category": t["category"],
         "risk_level": t["risk_level"], "enabled": t.get("enabled", False),
         "valid_phases": t["valid_phases"], "timeout_seconds": t["timeout_seconds"]}
        for t in tools
    ]}


@app.post("/runs/{run_id}/stop")
def stop_run(run_id: str) -> dict:
    """Stop run — run status stopped; eksekusi tool berikutnya ditolak runner."""
    row = db.query_one("SELECT status FROM workflow_runs WHERE id = %s", (run_id,))
    if row is None:
        raise HTTPException(404, "run tidak ditemukan")
    db.execute(
        "UPDATE workflow_runs SET status = 'stopped', finished_at = NOW() "
        "WHERE id = %s AND status IN ('pending','running')",
        (run_id,),
    )
    write_event(run_id, "cleanup_verification", "run_stopped", {"by": "api"})
    audit(None, "run_stopped", {"run_id": run_id}, run_id=run_id)
    return {"ok": True, "run_id": run_id, "status": "stopped"}
