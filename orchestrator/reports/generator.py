"""report.pdf generator — WeasyPrint (keputusan Phase 0, PROMPT.md Bagian 2/6).

Alur: kumpulkan data run + findings dari DB → render HTML template (jinja2)
→ WeasyPrint → simpan `storage/reports/{run_id}.pdf` → row `reports`.
Dipanggil otomatis saat run completed (ai/loop.py) atau manual via endpoint.
"""
from __future__ import annotations

import html
import json
import re
from pathlib import Path

import db
import findings as findings_mod
from config import settings
from tools.runner import write_event

_TEMPLATES_DIR = Path(__file__).resolve().parent / "templates"

# context jinja2 sengaja autoescape=False; semua nilai di-escape manual via _esc()
# karena record tool bisa berisi HTML — keamanan PDF = escape semua interpolasi.


def _esc(v) -> str:
    return html.escape(str(v if v is not None else ""), quote=True)


def _para_blocks(text: str) -> list[str]:
    return [p.strip() for p in (text or "").split("\n\n") if p.strip()]


def _fmt_duration(ms) -> str:
    try:
        s = round(int(ms) / 1000)
    except (TypeError, ValueError):
        return "—"
    m, sec = divmod(s, 60)
    return f"{m}m {sec:02d}s" if m else f"{sec}s"


def generate_report(run_id: str) -> dict:
    """Generate report.pdf utk run. Idempotent: row reports di-upsert, file dioverwrite."""
    run = db.query_one(
        "SELECT r.id, r.status, r.started_at, r.finished_at, r.error_message, "
        "t.value AS target_value, t.type AS target_type, t.environment, "
        "wt.name AS template_name, u.username AS operator "
        "FROM workflow_runs r "
        "JOIN targets t ON t.id = r.target_id "
        "JOIN workflow_templates wt ON wt.id = r.template_id "
        "LEFT JOIN users u ON u.id = r.created_by "
        "WHERE r.id = %s", (run_id,))
    if run is None:
        raise ValueError(f"run {run_id} tidak ditemukan")

    findings_rows = db.query(
        "SELECT id, severity, title, cvss_score, cwe, cve, summary, impact, "
        "remediation, evidence_path, `references`, source_tool, verification_status "
        "FROM findings WHERE run_id = %s "
        "ORDER BY FIELD(severity,'critical','high','medium','low','info'), id",
        (run_id,))

    tool_runs = []
    for r in db.query(
            "SELECT phase, payload FROM run_events WHERE run_id=%s AND "
            "type='tool_completed' ORDER BY id", (run_id,)):
        p = r["payload"]
        if isinstance(p, str):
            try:
                p = json.loads(p)
            except json.JSONDecodeError:
                p = {}
        tool_runs.append({
            "phase": r["phase"], "tool": p.get("tool", "?"),
            "duration": _fmt_duration(p.get("elapsed_ms")),
            "records": p.get("record_count", 0),
        })

    # ---- recon inventory (dari artefak tool di evidence dir) ----
    # subfinder → semua subdomain; httpx → host live + status/title/tech;
    # cms_check → CMS/versi/stack per host. Digabung jadi tabel host utk report.
    ev_dir = settings.EVIDENCE_DIR / run_id
    subdomains: list[str] = []
    sf = ev_dir / "subfinder_hosts.txt"
    if sf.is_file():
        subdomains = [ln.strip() for ln in sf.read_text(encoding="utf-8").splitlines() if ln.strip()]
    httpx_recs: list[dict] = []
    hx = ev_dir / "httpx_results.json"
    if hx.is_file():
        try:
            httpx_recs = json.loads(hx.read_text(encoding="utf-8"))
        except (json.JSONDecodeError, OSError):
            httpx_recs = []
    cms_recs: dict[str, dict] = {}
    cc = ev_dir / "cms_check_results.json"
    if cc.is_file():
        try:
            for ln in cc.read_text(encoding="utf-8").splitlines():
                ln = ln.strip()
                if not ln:
                    continue
                r = json.loads(ln)
                if isinstance(r, dict) and r.get("host"):
                    cms_recs[str(r["host"])] = r
        except (json.JSONDecodeError, OSError):
            pass

    httpx_by_host: dict[str, dict] = {}
    for r in httpx_recs:
        h = str(r.get("host") or r.get("input") or "").strip()
        if h:
            httpx_by_host[h] = r
    live_hosts = sorted(httpx_by_host.keys())

    def _tech_list(r: dict) -> str:
        t = r.get("tech")
        if isinstance(t, list):
            return ", ".join(str(x) for x in t[:6])
        return str(t or "")

    recon_hosts = []
    for h in sorted(set(subdomains + live_hosts)):
        hx_r = httpx_by_host.get(h, {})
        cm = cms_recs.get(h, {})
        recon_hosts.append({
            "host": _esc(h),
            "live": h in live_hosts,
            "ip": _esc(hx_r.get("host_ip") or "—"),
            "status": _esc(hx_r.get("status_code") or "—"),
            "title": _esc(hx_r.get("title") or "—"),
            "tech": _esc(_tech_list(hx_r) or "—"),
            "webserver": _esc(hx_r.get("webserver") or "—"),
            "cdn": _esc(hx_r.get("cdn_name") or "—"),
            "scheme": _esc(hx_r.get("scheme") or "—"),
            "cms": _esc(cm.get("cms") or "—"),
            "cms_version": _esc(cm.get("cms_version") or "—"),
            "php_version": _esc(cm.get("php_version") or "—"),
            "framework": _esc(cm.get("framework") or "—"),
            "nuclei_templates": _esc(", ".join(str(x) for x in (cm.get("nuclei_templates") or [])[:5]) or "—"),
        })
    cms_detected = any(rh["cms"] != "—" for rh in recon_hosts)

    # overall summary: dari row reports (kalau sudah ada) atau generate baru
    report_row = db.query_one(
        "SELECT overall_summary FROM reports WHERE run_id=%s", (run_id,))
    if report_row and report_row.get("overall_summary"):
        overall = str(report_row["overall_summary"])
    else:
        overall = findings_mod.generate_overall_summary(run_id)

    # ---- severity table ----
    sev_order = ["critical", "high", "medium", "low", "info"]
    counts = {s: 0 for s in sev_order}
    for f in findings_rows:
        counts[f["severity"]] = counts.get(f["severity"], 0) + 1
    total = len(findings_rows)
    sev_table = [
        {"sev": s, "count": counts[s],
         "pct": round(counts[s] * 100 / total) if total else 0}
        for s in sev_order
    ]

    # ---- findings siap-escape ----
    f_items = []
    for f in findings_rows:
        refs = f.get("references")
        if isinstance(refs, str):
            try:
                refs = json.loads(refs)
            except json.JSONDecodeError:
                refs = []
        f_items.append({
            "id": _esc(f["id"]),
            "severity": _esc(f["severity"]),
            "title": _esc(f["title"]),
            "cvss_score": _esc(f["cvss_score"]),
            "cwe": _esc(f["cwe"]),
            "cve": _esc(f["cve"]),
            "summary": _esc(f["summary"]),
            "impact": _esc(f["impact"]),
            "remediation": _esc(f["remediation"]),
            "evidence_path": _esc(f["evidence_path"]),
            "source_tool": _esc(f["source_tool"]),
            "references": [_esc(r) for r in (refs or [])[:10]],
        })

    summary_paras = [_esc(p) for p in _para_blocks(overall)]

    now_row = db.query_one("SELECT NOW() AS n")
    generated_at = str(now_row["n"])[:19] if now_row else ""

    # ---- render jinja2 (sandbox via manual escape — semua nilai sudah _esc) ----
    from jinja2 import Environment, FileSystemLoader, select_autoescape
    env = Environment(
        loader=FileSystemLoader(str(_TEMPLATES_DIR)),
        autoescape=select_autoescape(default=False),
    )
    tpl = env.get_template("report_template.html")
    html_doc = tpl.render(
        run_id=_esc(run_id),
        target=_esc(run["target_value"]),
        environment=_esc(str(run["environment"]).upper()),
        template=_esc(run["template_name"]),
        operator=_esc(run["operator"] or "system"),
        started_at=_esc(str(run["started_at"])[:19].replace("T", " ")) + " UTC",
        finished_at=_esc(str(run["finished_at"])[:19].replace("T", " ")) + " UTC",
        generated_at=_esc(generated_at),
        findings_total=total,
        summary_paras=summary_paras,
        recon_hosts=recon_hosts,
        subdomains_total=len(subdomains),
        live_hosts_total=len(live_hosts),
        cms_detected=cms_detected,
        sev_table=sev_table,
        findings=f_items,
        tool_runs=[{
            "phase": _esc(t["phase"]), "tool": _esc(t["tool"]),
            "duration": _esc(t["duration"]), "records": _esc(t["records"]),
        } for t in tool_runs],
    )

    # ---- weasyprint → PDF ----
    from weasyprint import HTML
    settings.REPORTS_DIR.mkdir(parents=True, exist_ok=True)
    pdf_path = settings.REPORTS_DIR / f"{run_id}.pdf"
    HTML(string=html_doc).write_pdf(str(pdf_path))

    rel_path = f"storage/reports/{run_id}.pdf"
    size_kb = max(1, round(pdf_path.stat().st_size / 1024))
    db.execute(
        "INSERT INTO reports (run_id, file_path, overall_summary, generated_at) "
        "VALUES (%s, %s, %s, NOW()) "
        "ON DUPLICATE KEY UPDATE file_path=VALUES(file_path), "
        "overall_summary=VALUES(overall_summary), generated_at=NOW()",
        (run_id, rel_path, overall),
    )
    write_event(run_id, "report_generation", "report_generated", {
        "file": rel_path, "size_kb": size_kb,
        "findings": total,
        "message": f"report.pdf dibuat ({size_kb} KB, {total} findings)",
    })
    return {"pdf": str(pdf_path), "size_kb": size_kb, "findings": total}
