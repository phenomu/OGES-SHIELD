"""Tool runner — eksekusi subprocess dengan array args (shell=False) + scope guard.

Guardrail 9 (PROMPT.md):
  1. Scope enforcement server-side: target wajib in_scope=true milik run yang sah.
  2. AI/user TIDAK menulis command bebas — command dirakit dari command_template
     + parameter tervalidasi; parameter hanya mengisi placeholder {name}.
  3. subprocess.run(list, shell=False) — tidak ada shell string concat.
"""
from __future__ import annotations

import json
import re
import shutil
import subprocess
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

import db
from config import settings
from tools.registry import ToolRegistryError, registry


class ScopeViolation(Exception):
    """Target tidak terdaftar in-scope — eksekusi DITOLAK (guardrail 9.1)."""


class ToolValidationError(Exception):
    """Parameter tool tidak lolos validasi input_schema."""


def _now() -> str:
    return datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S.%f")[:-3]


# ---------------------------------------------------------------- events ----

def write_event(run_id: str, phase: str, type_: str, payload: dict) -> int:
    """Tulis run_events — sumber polling PHP /api/stream.php."""
    return db.insert_returning_id(
        "INSERT INTO run_events (run_id, phase, type, payload) VALUES (%s, %s, %s, %s)",
        (run_id, phase, type_, json.dumps(payload, default=str)),
    )


def audit(user_id: int | None, action: str, detail: dict,
          run_id: str | None = None, tool_id: str | None = None,
          target_value: str | None = None) -> None:
    try:
        db.execute(
            "INSERT INTO audit_logs (user_id, run_id, action, target_value, tool_id, detail) "
            "VALUES (%s, %s, %s, %s, %s, %s)",
            (user_id, run_id, action, target_value, tool_id, json.dumps(detail, default=str)),
        )
    except Exception as e:  # audit gagal tidak boleh putus alur
        print(f"[audit] gagal: {e}")


# ------------------------------------------------------------ validation ----

_PLACEHOLDER_RE = re.compile(r"\{(\w+)\}")
_FORBIDDEN = re.compile(r"[;&|`$><\n\r]")


def validate_params(tool: dict, params: dict) -> dict:
    """Validasi parameter terhadap input_schema tool (typed + enum)."""
    schema = tool["input_schema"]
    props: dict = schema.get("properties", {})
    required: list = schema.get("required", [])

    clean: dict[str, Any] = {}
    for key, value in (params or {}).items():
        if key not in props:
            raise ToolValidationError(f"parameter '{key}' tidak ada di schema tool")
        pdef = props[key]
        ptype = pdef.get("type", "string")
        if ptype == "string":
            if not isinstance(value, str):
                raise ToolValidationError(f"parameter '{key}' wajib string")
            if _FORBIDDEN.search(value):
                raise ToolValidationError(
                    f"parameter '{key}' mengandung karakter terlarang (shell metachar)"
                )
        elif ptype in ("number", "integer"):
            if not isinstance(value, (int, float)) or isinstance(value, bool):
                raise ToolValidationError(f"parameter '{key}' wajib number")
        elif ptype == "boolean":
            if not isinstance(value, bool):
                raise ToolValidationError(f"parameter '{key}' wajib boolean")
        elif ptype == "array":
            if not isinstance(value, list) or not all(isinstance(v, str) for v in value):
                raise ToolValidationError(f"parameter '{key}' wajib array of string")
            for v in value:
                if _FORBIDDEN.search(v):
                    raise ToolValidationError(
                        f"parameter '{key}' mengandung karakter terlarang (shell metachar)"
                    )
        else:
            raise ToolValidationError(f"tipe parameter '{key}' tidak didukung runner")
        if "enum" in pdef and value not in pdef["enum"]:
            raise ToolValidationError(
                f"nilai '{value}' tidak ada di enum parameter '{key}'"
            )
        clean[key] = value

    for req in required:
        if req not in clean:
            raise ToolValidationError(f"parameter wajib '{req}' tidak diberikan")
    return clean


# ----------------------------------------------------------------- scope ----

def assert_scope(run_id: str, tool: dict, params: dict) -> dict:
    """Guardrail 9.1 — cek nilai target ada di tabel targets in_scope milik run."""
    run = db.query_one(
        "SELECT id, target_id, status FROM workflow_runs WHERE id = %s", (run_id,)
    )
    if run is None:
        raise ScopeViolation(f"run {run_id} tidak ditemukan")
    if run["status"] in ("completed", "stopped"):
        raise ScopeViolation(f"run {run_id} sudah {run['status']} — eksekusi ditolak")

    target = db.query_one(
        "SELECT id, type, value, environment, in_scope FROM targets WHERE id = %s",
        (run["target_id"],),
    )
    if target is None or not target["in_scope"]:
        audit(None, "scope_violation", {
            "run_id": run_id, "tool_id": tool["id"], "target_id": run["target_id"],
        }, run_id=run_id, tool_id=tool["id"])
        raise ScopeViolation(
            f"target id={run['target_id']} tidak in-scope — eksekusi ditolak (security event tercatat)"
        )

    # nilai parameter yang dipakai tool wajib cocok/mencakup target terdaftar
    declared = str(target["value"])
    ttype = str(target["type"])
    used_values = [v for v in params.values() if isinstance(v, str)]
    # array param (mis. template_tags) bukan nilai target — tidak ikut scope check
    # input_file: baca isi file, tiap host/baris jadi nilai yang di-scope-check
    file_hosts: list[str] = []
    for pname, pval in params.items():
        if pname == "input_file" and isinstance(pval, str):
            try:
                p = Path(pval)
                if p.is_file() and p.stat().st_size < 1_000_000:  # max 1MB
                    file_hosts = [
                        ln.strip() for ln in p.read_text(errors="replace").splitlines()
                        if ln.strip() and not ln.startswith("#")
                    ]
                    # host di file wajib di bawah domain terdaftar (atau = url/ip target)
                    used_values.extend(file_hosts)
                    # path file itu sendiri tidak dianggap target
                    used_values = [v for v in used_values if v != pval]
            except OSError:
                pass

    def _host_matches(v: str) -> bool:
        if ttype == "domain":
            # nilai polos / subdomain
            if v == declared or v.endswith("." + declared):
                return True
            # nilai URL (http://...) — host-nya wajib = domain/subdomain terdaftar
            try:
                from urllib.parse import urlparse
                host = urlparse(v).hostname or ""
                return host == declared or host.endswith("." + declared)
            except ValueError:
                return False
        if ttype == "url":
            # nilai = url sama / path di bawahnya; atau host:port polos
            if v == declared or v.startswith(declared.rstrip("/") + "/"):
                return True
            d = declared.rstrip("/")
            if v == d.rsplit("://", 1)[-1]:  # '127.0.0.1:8899' == '127.0.0.1:8899'
                return True
            # nilai domain polos utk url ip-only
            dh = d.rsplit("://", 1)[-1]
            if v == dh.split(":")[0] and dh.replace(":", "").replace(".", "").isdigit():
                return True
            return False
        if ttype == "ip":
            return v == declared or v.startswith(declared.rstrip("/") + "/")
        if ttype == "cidr":
            import ipaddress
            try:
                net = ipaddress.ip_network(declared, strict=False)
                host = v.split("/")[0].split(":")[0]
                return str(ipaddress.ip_address(host)) in net
            except ValueError:
                return False
        return False

    ok = any(_host_matches(v) for v in used_values)

    if not ok:
        audit(None, "scope_violation", {
            "run_id": run_id, "tool_id": tool["id"],
            "declared_target": declared, "used": used_values[:20],
        }, run_id=run_id, tool_id=tool["id"])
        raise ScopeViolation(
            f"parameter tool {used_values[:5]} tidak mencakup target terdaftar '{declared}'"
        )
    return target


    try:
        ipaddress.ip_address(value)
        return True
    except ValueError:
        return False


# ----------------------------------------------------------------- run ------

def _resolve_binary(command_template: str) -> tuple[str, str]:
    """Pecah template: (binary_name, args_template). Cari binary di PATH / bin/."""
    parts = command_template.strip().split(maxsplit=1)
    binary = parts[0]
    args_t = parts[1] if len(parts) > 1 else ""

    # binari go di bin/ (GOBIN) — dipasang scripts/setup.sh
    candidate = settings.BIN_DIR / binary
    if candidate.is_file() and candidate.is_file():
        return str(candidate), args_t
    if shutil.which(binary):
        return binary, args_t
    raise ToolValidationError(
        f"binary '{binary}' tidak ditemukan di PATH / {settings.BIN_DIR} — jalankan scripts/setup.sh"
    )


def _parse_output(parser: str, stdout: str) -> list[dict]:
    """Parse output tool → list record. Parser toleran: baris rusak di-skip."""
    records: list[dict] = []
    if parser == "jsonlines":
        for line in stdout.splitlines():
            line = line.strip()
            if not line:
                continue
            try:
                records.append(json.loads(line))
            except json.JSONDecodeError:
                continue
    elif parser == "json":
        try:
            data = json.loads(stdout)
            records = data if isinstance(data, list) else [data]
        except json.JSONDecodeError:
            records = [{"raw": stdout[:2000]}]
    else:  # text
        records = [{"raw": line} for line in stdout.splitlines() if line.strip()]
    return records


_CUSTOM_TOOLS: dict[str, str] = {
    # tool_id → file tool python custom (script_path diisi orchestrator, bukan AI)
    "custom_sqli_probe": "sqli_probe.py",
    "custom_wp_check": "wp_check.py",
    "custom_php_check": "php_check.py",
    "custom_php2shell": "php2shell.py",
    "custom_cms_check": "cms_check.py",
}


def inject_script_paths(tool_id: str, params: dict) -> dict:
    """Tool python custom: script_path absolut diisi orchestrator (bukan AI/caller).
    Nilai script_path dari AI/caller SELALU dibuang (guardrail 9.2 — path injection)."""
    fname = _CUSTOM_TOOLS.get(tool_id)
    if fname:
        params = dict(params)
        params["script_path"] = str(Path(__file__).resolve().parent / fname)
    return params


def run_tool(run_id: str, tool_id: str, params: dict, user_id: int | None = None,
             phase: str = "manual") -> dict:
    """Eksekusi 1 tool utk run. Return ringkasan hasil (dict) — caller menulis event."""
    tool = registry.get(tool_id)
    if tool is None:
        raise ToolRegistryError(f"tool '{tool_id}' tidak terdaftar")
    if not tool.get("enabled"):
        raise ToolValidationError(f"tool '{tool_id}' disabled")

    # script_path utk tool python custom diisi orchestrator (bukan AI/caller)
    params = inject_script_paths(tool_id, dict(params))

    clean = validate_params(tool, params)
    target = assert_scope(run_id, tool, clean)  # guardrail 9.1

    binary, args_template = _resolve_binary(tool["command_template"])
    # rakit argumen dari template — placeholder {name} diisi param tervalidasi
    tokens = args_template.split()
    args: list[str] = []
    i = 0
    while i < len(tokens):
        tok = tokens[i]
        m = _PLACEHOLDER_RE.fullmatch(tok)
        if m:
            v = clean.get(m.group(1), "")  # param opsional tak diisi → kosong
            if isinstance(v, list):
                v = ",".join(v)  # array param → comma-separated (mis. tags nuclei)
            v = str(v)
            # placeholder kosong SETELAH flag (-xxx) → buang flag juga (skip pasangan)
            if v == "" and i > 0 and tokens[i - 1].startswith("-"):
                args.pop()  # flag sebelumnya
                i += 1
                continue
            if v == "":
                i += 1
                continue  # placeholder kosong tanpa flag → skip (jangan kirim argv kosong)
            args.append(v)
        elif "{" in tok or "}" in tok:
            raise ToolValidationError(f"template token rusak: {tok}")
        else:
            args.append(tok)
        i += 1

    started_ts = datetime.now(timezone.utc)
    write_event(run_id, phase, "tool_started", {
        "tool": tool_id, "binary": Path(binary).name,
        "args": args, "target": target["value"], "at": started_ts.isoformat(),
    })

    try:
        proc = subprocess.run(  # noqa: S603 — args list + shell=False
            [binary] + args,
            capture_output=True, text=True, timeout=tool["timeout_seconds"],
            cwd=str(settings.EVIDENCE_DIR.parent), shell=False,
        )
    except subprocess.TimeoutExpired:
        write_event(run_id, phase, "tool_failed", {
            "tool": tool_id, "error": f"timeout {tool['timeout_seconds']}s",
        })
        audit(user_id, "tool_failed", {"tool": tool_id, "reason": "timeout"}, run_id=run_id,
              tool_id=tool_id, target_value=target["value"])
        return {"ok": False, "error": "timeout", "tool": tool_id}

    elapsed_ms = int((datetime.now(timezone.utc) - started_ts).total_seconds() * 1000)
    records = _parse_output(tool["output_parser"], proc.stdout or "")

    result = {
        "ok": proc.returncode == 0,
        "tool": tool_id,
        "returncode": proc.returncode,
        "elapsed_ms": elapsed_ms,
        "records": records,
        "record_count": len(records),
        "stdout_tail": (proc.stdout or "")[-2000:],
        "stderr_tail": (proc.stderr or "")[-500:],
        "started_at": started_ts.isoformat(),
        "finished_at": _now(),
    }

    write_event(run_id, phase, "tool_completed" if result["ok"] else "tool_failed", {
        "tool": tool_id, "returncode": proc.returncode,
        "records": records if len(records) <= 50 else None,  # payload ringkas: max 50 rec
        "record_count": len(records),
        "elapsed_ms": elapsed_ms,
        "stdout_tail": result["stdout_tail"],
        "stderr_tail": result["stderr_tail"],
    })
    audit(user_id, "tool_executed", {
        "tool": tool_id, "returncode": proc.returncode,
        "records": len(records), "phase": phase,
    }, run_id=run_id, tool_id=tool_id, target_value=target["value"])

    return result
