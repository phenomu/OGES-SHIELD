"""AI Orchestration Loop — inti produk OGES-SHIELD (PROMPT.md Bagian 5).

Loop per phase:
  1. Ambil tools valid utk phase (enabled=true)
  2. Panggil GLM-5.3: system prompt (peran, phase, target, running summary)
     + tools param (schema turunan otomatis dari tools.json) + tool_choice=required
  3. Eksekusi tool pilihan AI (runner — scope+validasi tetap di server, guardrail 9)
  4. Hasil → GLM summary (ai_summary event)
  5. Ulangi sampai AI panggil mark_phase_complete ATAU safety limit
     (MAX_TOOL_CALLS_PER_PHASE / MAX_TIME_PER_PHASE_SECONDS — loop TIDAK bisa infinite)
  6. Lanjut phase berikutnya; selesai semua → run completed.

Destructive tool → run awaiting_confirmation, loop pause sampai user konfirmasi.
"""
from __future__ import annotations

import json
import time
from typing import Any

import db
import findings
from ai.client import AIError, client
from config import settings
from tools.registry import registry
from tools.runner import (
    ScopeViolation,
    ToolValidationError,
    audit,
    run_tool,
    write_event,
)


class LoopStopped(Exception):
    """Run dihentikan user (status stopped)."""


# ---------------------------------------------------------------- helpers ----

def _tools_param(phase: str, exclude: set[str] | None = None) -> list[dict]:
    """OpenAI function schema, diturunkan otomatis dari tools.json (guardrail 9.2:
    AI hanya melihat schema — tidak pernah melihat command template).
    exclude: tool yang ditolak user saat confirm — tidak ditawarkan lagi."""
    out: list[dict] = []
    for t in registry.for_phase(phase, enabled_only=True):
        if exclude and t["id"] in exclude:
            continue
        out.append({
            "type": "function",
            "function": {
                "name": t["id"],
                "description": f"{t['name']} — {t.get('description', '')}",
                "parameters": t["input_schema"],
            },
        })
    return out


_MARK_PHASE_COMPLETE = {
    "type": "function",
    "function": {
        "name": "mark_phase_complete",
        "description": (
            "Tandai phase saat ini SELESAI karena informasi yang dikumpulkan sudah cukup "
            "untuk lanjut ke phase berikutnya. Panggil kalau tool yang tersisa tidak akan "
            "menambah informasi baru yang berarti."
        ),
        "parameters": {"type": "object", "properties": {}},
    },
}


def _system_prompt(phase: str, target: dict, template_name: str, run_id: str) -> str:
    return (
        "Kamu adalah AI orchestrator penetration testing di platform OGES-SHIELD. "
        "Tugas kamu di phase ini: memilih tool berikutnya yang PALING relevan untuk "
        f"phase '{phase}', atau mark_phase_complete kalau data sudah cukup.\n\n"
        f"Run: {run_id}\n"
        f"Workflow template: {template_name}\n"
        f"Target (satu-satunya scope yang sah): {target['value']} (tipe: {target['type']}, "
        f"environment: {target['environment']})\n\n"
        "Aturan:\n"
        "- Pilih SATU tool per keputusan via function call. Parameter wajib valid sesuai schema.\n"
        f"- Target terdaftar untuk run ini: {target['value']} (tipe {target['type']}). "
        "Parameter domain/target_url WAJIB bernilai target itu atau subdomain/path dari target itu. "
        "Parameter lain dari target = DITOLAK scope guard server-side (lihat SCOPE VIOLATION di hasil). "
        "Kalau tool terakhir ditolak scope, JANGAN ulangi parameter sama — koreksi atau pilih tool lain.\n"
        "- Jangan ulangi tool yang sudah memberikan hasil bila tidak ada alasan (mis. input baru).\n"
        "- Kalau informasi phase ini sudah cukup → panggil mark_phase_complete.\n"
        "- Kalau tool gagal, pertimbangkan tool lain atau mark_phase_complete dengan alasan.\n"
        "Balas ringkas. Alasan keputusan di properti 'reason' tidak dipakai — gunakan tool call."
    )


def _running_summary(run_id: str) -> str:
    """Ringkasan hasil sejauh ini (bukan raw log — jaga context kecil)."""
    rows = db.query(
        "SELECT phase, type, payload FROM run_events WHERE run_id = %s "
        "AND type IN ('tool_completed','tool_failed','ai_summary','security_event') "
        "ORDER BY id DESC LIMIT 12",
        (run_id,),
    )
    if not rows:
        return "(belum ada hasil)"
    lines: list[str] = []
    for r in rows:
        p = r["payload"]
        if isinstance(p, str):
            try:
                p = json.loads(p)
            except Exception:
                p = {}
        if r["type"] == "tool_completed":
            lines.append(f"- [{r['phase']}] {p.get('tool','?')}: ok records={p.get('record_count','?')}")
        elif r["type"] == "tool_failed":
            lines.append(f"- [{r['phase']}] {p.get('tool','?')}: GAGAL {p.get('error','')}")
        elif r["type"] == "security_event":
            lines.append(f"- [{r['phase']}] {p.get('tool','?')}: SCOPE VIOLATION — parameter "
                         f"ditolak: {str(p.get('detail',''))[:150]}")
        else:
            s = str(p.get("summary", ""))[:150]
            lines.append(f"- [{r['phase']}] AI: {s}")
    return "\n".join(reversed(lines))


def _extract_tool_call(message: dict) -> tuple[str, dict] | None:
    calls = message.get("tool_calls") or []
    if not calls:
        return None
    fn = calls[0].get("function", {})
    name = fn.get("name", "")
    try:
        args = json.loads(fn.get("arguments") or "{}")
    except json.JSONDecodeError:
        args = {}
    return name, args


def _persist_artifacts(run_id: str, tool_id: str, result: dict) -> str | None:
    """Simpan output tool jadi file artefak yang bisa dipakai tool berikutnya.
    subfinder → daftar host (dipakai httpx -l). Return path atau None."""
    if tool_id != "subfinder":
        return None
    hosts: list[str] = []
    for r in result.get("records") or []:
        h = r.get("host") or r.get("input") or ""
        if h:
            hosts.append(str(h).strip())
    if not hosts:
        return None
    out_dir = settings.EVIDENCE_DIR / run_id
    out_dir.mkdir(parents=True, exist_ok=True)
    path = out_dir / "subfinder_hosts.txt"
    path.write_text("\n".join(sorted(set(hosts))) + "\n", encoding="utf-8")
    return str(path)


def _artifacts_hint(run_id: str) -> str:
    """Daftar artefak file hasil tool (utk konteks AI)."""
    out_dir = settings.EVIDENCE_DIR / run_id
    if not out_dir.is_dir():
        return ""
    files = [str(p) for p in sorted(out_dir.iterdir()) if p.is_file()]
    if not files:
        return ""
    return "\nArtefak file hasil tool (path absolut — bisa dipakai sebagai parameter input_file):\n" + \
        "\n".join(f"- {f}" for f in files)


def _inject_script_paths(tool_id: str, params: dict) -> dict:
    """Tool python custom: script_path diisi orchestrator (bukan AI)."""
    if tool_id == "custom_sqli_probe" and "script_path" not in params:
        params = dict(params)
        params["script_path"] = str(
            settings.EVIDENCE_DIR.parent.parent / "orchestrator" / "tools" / "sqli_probe.py"
        )
    return params


# ------------------------------------------------------------------- loop ----

def _run_status(run_id: str) -> str:
    row = db.query_one("SELECT status FROM workflow_runs WHERE id = %s", (run_id,))
    return str(row["status"]) if row else "stopped"


def _should_stop(run_id: str) -> bool:
    return _run_status(run_id) in ("stopped", "failed", "completed")


def _wait_for_confirmation(run_id: str, tool_id: str, phase: str) -> bool:
    """Destructive tool: pause sampai user konfirmasi (guardrail 9.4). Return True=lanjut."""
    write_event(run_id, phase, "confirmation_required", {
        "tool": tool_id, "message": (
            f"Tool '{tool_id}' berisiko destructive — run pause menunggu konfirmasi user."
        ),
    })
    waited = 0
    while waited < settings.MAX_TIME_PER_PHASE_SECONDS:
        st = _run_status(run_id)
        if st == "stopped":
            raise LoopStopped(run_id)
        if st != "awaiting_confirmation":
            # user confirm (status kembali running) atau reject (completed/stopped)
            row = db.query_one(
                "SELECT payload FROM run_events WHERE run_id=%s AND type='confirmation_result' "
                "ORDER BY id DESC LIMIT 1", (run_id,))
            if row:
                p = row["payload"]
                if isinstance(p, str):
                    try:
                        p = json.loads(p)
                    except Exception:
                        p = {}
                return bool(p.get("confirmed", False))
            return True
        time.sleep(2)
        waited += 2
    return False  # timeout → tolak destructive


def run_workflow_loop(run_id: str) -> None:
    """Jalankan seluruh workflow run (dipanggil sebagai thread dari POST /runs)."""
    run = db.query_one(
        "SELECT r.id, r.phases, r.created_by, wt.name AS template_name "
        "FROM workflow_runs r JOIN workflow_templates wt ON wt.id = r.template_id "
        "WHERE r.id = %s", (run_id,))
    if run is None:
        return
    phases = run["phases"]
    if isinstance(phases, str):
        try:
            phases = json.loads(phases)
        except json.JSONDecodeError:
            phases = []

    target = db.query_one(
        "SELECT t.value, t.type, t.environment FROM workflow_runs r "
        "JOIN targets t ON t.id = r.target_id WHERE r.id = %s", (run_id,))
    if target is None:
        _finish(run_id, "failed", "target hilang")
        return

    db.execute("UPDATE workflow_runs SET status='running', started_at=NOW() "
               "WHERE id=%s AND status='pending'", (run_id,))

    try:
        for phase in phases:
            if _should_stop(run_id):
                raise LoopStopped(run_id)
            _run_phase(run_id, phase, target, str(run["template_name"]))
        _finish(run_id, "completed", None)
    except LoopStopped:
        write_event(run_id, str(phases[-1]) if phases else "manual", "run_stopped",
                    {"by": "user"})
    except Exception as e:  # phase fatal → run failed, bukan crash service
        _finish(run_id, "failed", str(e)[:500])
        audit(run.get("created_by"), "run_failed", {"run_id": run_id, "error": str(e)[:300]},
              run_id=run_id)


def _finish(run_id: str, status: str, error: str | None) -> None:
    db.execute("UPDATE workflow_runs SET status=%s, finished_at=NOW(), "
               "error_message=%s WHERE id=%s", (status, error, run_id))
    if status == "completed":
        write_event(run_id, "report_generation", "run_completed", {})
        # Phase 5: overall summary + report.pdf otomatis di akhir workflow
        try:
            from reports.generator import generate_report
            generate_report(run_id)
        except Exception as e:  # report gagal → run tetap completed, bisa retry manual
            write_event(run_id, "report_generation", "error",
                        {"error": f"report generation: {e}"})
    elif status == "failed":
        write_event(run_id, "report_generation", "error", {"error": (error or "")[:300]})


def _run_phase(run_id: str, phase: str, target: dict, template_name: str) -> None:
    db.execute("UPDATE workflow_runs SET current_phase=%s WHERE id=%s", (phase, run_id))
    write_event(run_id, phase, "phase_started", {"phase": phase})

    phase_started = time.monotonic()
    tool_calls = 0
    consecutive_scope_violations = 0
    rejected_tools: set[str] = set()  # tool destructive yang ditolak user

    # phase tanpa tool terdaftar → selesaikan otomatis (mis. scope_validation, cleanup)
    while True:
        if _should_stop(run_id):
            raise LoopStopped(run_id)

        elapsed = time.monotonic() - phase_started
        if tool_calls >= settings.MAX_TOOL_CALLS_PER_PHASE:
            write_event(run_id, phase, "phase_complete", {
                "phase": phase, "reason": f"safety limit: max_tool_calls_per_phase "
                f"({settings.MAX_TOOL_CALLS_PER_PHASE}) tercapai",
            })
            return
        if elapsed >= settings.MAX_TIME_PER_PHASE_SECONDS:
            write_event(run_id, phase, "phase_complete", {
                "phase": phase, "reason": f"safety limit: max_time_per_phase_seconds "
                f"({settings.MAX_TIME_PER_PHASE_SECONDS}) tercapai",
            })
            return

        tools = _tools_param(phase, rejected_tools)
        if not tools:
            write_event(run_id, phase, "phase_complete", {
                "phase": phase, "reason": "tidak ada tool valid utk phase ini"
                + (f" (tool ditolak user: {sorted(rejected_tools)})" if rejected_tools else ""),
            })
            return

        # --- 1. AI pilih tool (tool_choice required) ---
        sys_prompt = _system_prompt(phase, target, template_name, run_id)
        user_msg = (
            f"Phase: {phase}\nTarget: {target['value']}\n"
            f"Tool calls terpakai: {tool_calls}/{settings.MAX_TOOL_CALLS_PER_PHASE}\n"
            f"Waktu phase: {int(elapsed)}s/{settings.MAX_TIME_PER_PHASE_SECONDS}s\n\n"
            "Hasil sejauh ini:\n" + _running_summary(run_id) +
            _artifacts_hint(run_id) +
            "\n\nPilih tool berikutnya atau mark_phase_complete."
        )
        try:
            res = client.chat_with_tools(
                [{"role": "system", "content": sys_prompt},
                 {"role": "user", "content": user_msg}],
                tools=tools + [_MARK_PHASE_COMPLETE],
                tool_choice="required",
                max_tokens=500,
            )
        except AIError as e:
            write_event(run_id, phase, "error", {
                "error": f"AI gagal: {e}", "phase": phase,
            })
            # AI down → phase selesai paksa (bukan infinite retry)
            write_event(run_id, phase, "phase_complete", {
                "phase": phase, "reason": f"AI tidak tersedia: {str(e)[:200]}",
            })
            return

        choice = _extract_tool_call(res["message"])
        if choice is None:
            # model balas teks tanpa tool call → anggap phase cukup
            write_event(run_id, phase, "phase_complete", {
                "phase": phase, "reason": "AI tidak memilih tool (respon teks)",
            })
            return
        tool_id, params = choice

        # --- 2. mark_phase_complete? ---
        if tool_id == "mark_phase_complete":
            reason = str(params.get("reason") or "AI menilai data phase sudah cukup")
            write_event(run_id, phase, "ai_decision", {
                "tool": "mark_phase_complete", "reason": reason, "phase": phase,
            })
            write_event(run_id, phase, "phase_complete", {"phase": phase, "reason": reason})
            return

        # --- 3. eksekusi tool pilihan AI (guardrail 9 tetap divalidasi runner) ---
        tool_def = registry.get(tool_id)
        if tool_def is None or phase not in tool_def.get("valid_phases", []):
            write_event(run_id, phase, "ai_decision", {
                "tool": tool_id, "reason": "tool tidak valid utk phase — diabaikan",
                "phase": phase, "invalid": True,
            })
            tool_calls += 1
            continue

        write_event(run_id, phase, "ai_decision", {
            "tool": tool_id, "reason": str(params), "phase": phase,
            "iteration": tool_calls + 1,
        })

        # destructive → pause + konfirmasi user (guardrail 9.4)
        if tool_def.get("risk_level") == "destructive":
            db.execute("UPDATE workflow_runs SET status='awaiting_confirmation' "
                       "WHERE id=%s", (run_id,))
            confirmed = _wait_for_confirmation(run_id, tool_id, phase)
            db.execute("UPDATE workflow_runs SET status='running' WHERE id=%s "
                       "AND status='awaiting_confirmation'", (run_id,))
            if not confirmed:
                write_event(run_id, phase, "tool_skipped", {
                    "tool": tool_id, "reason": "user menolak konfirmasi / timeout",
                })
                rejected_tools.add(tool_id)  # jangan tawarkan lagi di phase ini
                tool_calls += 1
                continue

        params = _inject_script_paths(tool_id, params)
        tool_calls += 1
        try:
            result = run_tool(run_id=run_id, tool_id=tool_id, params=params,
                              user_id=None, phase=phase)
        except ScopeViolation as e:
            write_event(run_id, phase, "security_event", {
                "tool": tool_id, "detail": str(e)[:300],
            })
            consecutive_scope_violations += 1
            if consecutive_scope_violations >= 3:
                write_event(run_id, phase, "phase_complete", {
                    "phase": phase, "reason": "AI berulang kali memilih parameter di luar "
                    "scope (3x) — phase dihentikan demi safety",
                })
                return
            continue  # AI kasih param out-of-scope → laporkan, biarkan AI koreksi
        except (ToolValidationError, Exception) as e:
            write_event(run_id, phase, "tool_failed", {
                "tool": tool_id, "error": str(e)[:300],
            })
            continue
        consecutive_scope_violations = 0

        if not result.get("ok"):
            continue

        # persist artefak (mis. subfinder hosts) → dipakai tool berikutnya
        _persist_artifacts(run_id, tool_id, result)

        # --- 4. AI summary hasil tool (event ai_summary) ---
        _summarize_tool_result(run_id, phase, tool_id, result, target)

        # --- 4b. map hasil → findings + AI enrich (Phase 5, Bagian 6 PROMPT.md) ---
        _ingest_findings(run_id, phase, tool_id, result)

    # unreachable


def _ingest_findings(run_id: str, phase: str, tool_id: str, result: dict) -> None:
    """Output tool → tabel findings + AI enrichment (Phase 5). Tidak boleh
    menggagalkan run kalau mapping/AI error — findings mentah tetap tersimpan."""
    try:
        ids = findings.map_tool_result(run_id, tool_id, result)
    except Exception as e:  # mapping gagal → log, lanjut run
        write_event(run_id, phase, "error", {"error": f"findings mapping: {e}"})
        return
    if not ids:
        return
    findings.write_findings_event(run_id, phase, tool_id, ids)
    try:
        n = findings.enrich_findings(run_id, ids)
        write_event(run_id, phase, "ai_summary", {
            "tool": "findings_enrichment",
            "summary": f"{n}/{len(ids)} finding diperkaya AI (severity divalidasi ulang, "
                       "summary/impact/remediation diisi)",
        })
    except Exception as e:
        write_event(run_id, phase, "error", {"error": f"findings enrich: {e}"})


def _summarize_tool_result(run_id: str, phase: str, tool_id: str,
                           result: dict, target: dict) -> None:
    """Panggilan ringan: ringkas hasil 1 tool → event ai_summary."""
    records = result.get("records") or []
    sample = records[:25]
    payload_hint = json.dumps(sample, default=str)[:3000]
    try:
        summary = client.chat_simple(
            "Kamu analis keamanan. Ringkas hasil tool pentest dalam 2-3 kalimat bahasa "
            "Indonesia: apa yang ditemukan, temuan penting, dan saran tool berikutnya. "
            "Fakta saja, tanpa embel-embel.",
            f"Tool: {tool_id}\nPhase: {phase}\nTarget: {target['value']}\n"
            f"Jumlah record: {result.get('record_count', 0)}\n"
            f"Contoh hasil (JSON, max 25):\n{payload_hint}",
            max_tokens=300,
        )
        write_event(run_id, phase, "ai_summary", {"tool": tool_id, "summary": summary})
    except AIError:
        write_event(run_id, phase, "ai_summary", {
            "tool": tool_id, "summary": "(AI summary tidak tersedia)",
        })
