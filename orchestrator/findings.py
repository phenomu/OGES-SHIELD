"""Findings mapper + AI enrichment (PROMPT.md Bagian 6 — Phase 5).

Alur:
  tool output (nuclei jsonlines / sqli_probe json)
    → dipetakan otomatis ke baris `findings` (1 hasil positif = 1 finding)
    → AI memperkaya summary/impact/remediation + validasi ulang severity
    → Overall Summary (agregat semua findings + rekomendasi) dipakai report.pdf
"""
from __future__ import annotations

import json
import re
from typing import Any

import db
from ai.client import AIError, client
from config import settings
from tools.runner import write_event

_SEV_MAP = {"critical": "critical", "high": "high", "medium": "medium",
            "low": "low", "info": "info", "unknown": "info"}


def _norm_sev(s: Any) -> str:
    return _SEV_MAP.get(str(s or "").lower(), "info")


def _cvss_of(rec: dict) -> float | None:
    for k in ("cvss", "cvss-score", "cvss_score"):
        v = rec.get(k)
        if v is None:
            continue
        try:
            return round(float(v), 1)
        except (TypeError, ValueError):
            continue
    return None


def _norm_refs(rec: dict) -> list[str]:
    refs = rec.get("reference") or rec.get("references") or []
    if isinstance(refs, str):
        refs = [refs]
    out: list[str] = []
    for r in refs[:10]:
        if isinstance(r, str) and r.strip():
            out.append(r.strip()[:300])
    return out


def _first(pattern: str, rec: dict) -> str | None:
    m = re.search(pattern, json.dumps(rec, default=str))
    return m.group(0) if m else None


# --------------------------------------------------------------- mappers ----

def _map_nuclei(rec: dict) -> dict | None:
    """1 record nuclei (JSON line hasil match) → 1 dict finding. None kalau bukan match."""
    if not rec.get("template-id") and not rec.get("matched-at"):
        return None  # baris INF nuclei (log, bukan temuan)
    tid = str(rec.get("template-id") or "nuclei-match")
    info = rec.get("info") or {}
    sev = _norm_sev(rec.get("severity") or info.get("severity"))
    name = str(info.get("name") or rec.get("type") or tid)[:250]
    host = str(rec.get("host") or rec.get("matched-at") or "")[:250]
    desc = str(info.get("description") or "")[:2000]
    tags = info.get("tags") or []
    if isinstance(tags, list):
        desc += "\nTags: " + ", ".join(str(t) for t in tags[:8])
    refs = _norm_refs(info) or _norm_refs(rec)
    return {
        "severity": sev,
        "title": f"{name} @ {host}" if host else name,
        "cvss_score": _cvss_of(rec) or _cvss_of(info),
        "cwe": _first(r"CWE-\d{1,3}", info) or _first(r"CWE-\d{1,3}", rec),
        "cve": _first(r"CVE-\d{4}-\d{4,}", info) or _first(r"CVE-\d{4}-\d{4,}", rec),
        "summary": desc or f"Template {tid} match di {host}.",
        "impact": "",
        "remediation": "",
        "references": refs,
        "source_tool": "nuclei",
    }


def _map_sqli_probe(result: dict) -> list[dict]:
    """Output sqli_probe.py (json) → findings kalau ada parameter suspicious."""
    out: list[dict] = []
    if not result.get("vulnerable"):
        return out
    target = str(result.get("target") or "")
    for r in result.get("results") or []:
        if r.get("status") != "suspicious":
            continue
        out.append({
            "severity": "high",
            "title": f"Indikasi SQL injection di parameter '{r.get('param')}' ({target})",
            "cvss_score": None,
            "cwe": "CWE-89",
            "cve": None,
            "summary": (
                f"Probe boolean-based mendeteksi perbedaan panjang respons antara payload "
                f"AND 1=1 ({r.get('len_true')} bytes) vs AND 1=2 ({r.get('len_false')} bytes) "
                f"pada parameter '{r.get('param')}'. Teknik: {r.get('technique')}."
            ),
            "impact": "",
            "remediation": "",
            "references": [
                "https://owasp.org/www-community/attacks/SQL_Injection",
                "https://cwe.mitre.org/data/definitions/89.html",
            ],
            "source_tool": "custom_sqli_probe",
        })
    return out


def _map_wp_check(rec: dict) -> list[dict]:
    """Output wp_check.py → findings dari daftar CVE yang match versi WP terdeteksi."""
    out: list[dict] = []
    if not rec.get("is_wordpress"):
        return out
    target = str(rec.get("target") or "")
    ver = str(rec.get("wp_version") or "?")
    out.append({
        "severity": "info",
        "title": f"WordPress {ver} terdeteksi ({target})",
        "cvss_score": None, "cwe": None, "cve": None,
        "summary": (
            f"WordPress versi {ver} terdeteksi"
            + (f", theme '{rec.get('theme')}'" if rec.get("theme") else "")
            + f". Sumber deteksi: {', '.join(rec.get('notes') or [])[:200]}."
        ),
        "impact": "", "remediation": "Update WordPress core ke versi terbaru.",
        "references": ["https://wordpress.org/documentation/asy-updating/"],
        "source_tool": "custom_wp_check",
    })
    for c in rec.get("cves") or []:
        out.append({
            "severity": _norm_sev(c.get("sev")),
            "title": f"{c.get('title')} — WordPress {ver} ({target})",
            "cvss_score": None, "cwe": None, "cve": c.get("cve"),
            "summary": f"{c.get('desc')} Versi terdeteksi: {ver}.",
            "impact": "", "remediation": "Update WordPress core ke versi patched.",
            "references": [f"https://nvd.nist.gov/vuln/detail/{c.get('cve')}"],
            "source_tool": "custom_wp_check",
        })
    return out


def _map_php_check(rec: dict) -> list[dict]:
    """Output php_check.py → findings dari daftar CVE yang match versi PHP terdeteksi."""
    out: list[dict] = []
    if not rec.get("is_php"):
        return out
    target = str(rec.get("target") or "")
    ver = str(rec.get("php_version") or "?")
    out.append({
        "severity": "info",
        "title": f"PHP {ver} terdeteksi ({target})",
        "cvss_score": None, "cwe": None, "cve": None,
        "summary": f"Versi PHP {ver} terdeteksi dari header X-Powered-By. Server: {rec.get('server') or '?'}.",
        "impact": "", "remediation": "Sembunyikan X-Powered-By + update PHP ke versi terbaru.",
        "references": ["https://www.php.net/releases/"],
        "source_tool": "custom_php_check",
    })
    for c in rec.get("cves") or []:
        out.append({
            "severity": _norm_sev(c.get("sev")),
            "title": f"{c.get('title')} — PHP {ver} ({target})",
            "cvss_score": None, "cwe": None, "cve": c.get("cve"),
            "summary": f"{c.get('desc')} Versi terdeteksi: {ver}.",
            "impact": "", "remediation": "Update PHP ke versi patched / patch CVE terkait.",
            "references": [f"https://nvd.nist.gov/vuln/detail/{c.get('cve')}"],
            "source_tool": "custom_php_check",
        })
    return out


def _map_php2shell(rec: dict) -> list[dict]:
    """Output php2shell.py → finding kalau exploit path POC valid (benign reachability)."""
    out: list[dict] = []
    if not rec.get("exploit_path_valid"):
        return out
    return [{
        "severity": "critical",
        "title": f"Exploit path valid: {rec.get('poc_name')} ({rec.get('target')})",
        "cvss_score": None, "cwe": None, "cve": rec.get("cve"),
        "summary": (
            f"Validasi POC benign menunjukkan exploit path terjangkau. "
            f"Evidence: {rec.get('evidence')}. {rec.get('poc_note')}"
        ),
        "impact": "Path exploit dapat dijangkau — potensi RCE kalau payload nyata dipakai (tidak dieksekusi di platform ini).",
        "remediation": "Patch CVE terkait + batasi akses path/endpoint tersebut.",
        "references": [f"https://nvd.nist.gov/vuln/detail/{rec.get('cve')}"] if rec.get("cve") else [],
        "source_tool": "custom_php2shell",
    }]


# -------------------------------------------------------------- insertion ----

def map_tool_result(run_id: str, tool_id: str, result: dict) -> list[int]:
    """Map output tool → baris findings. Return list id finding baru (kosong = tidak ada).

    Dipanggil loop setelah tool ok. Dedupe: (run_id, tool, title) sama → skip.
    """
    records = result.get("records") or []
    rows: list[dict] = []

    if tool_id == "nuclei":
        for rec in records:
            f = _map_nuclei(rec if isinstance(rec, dict) else {})
            if f:
                rows.append(f)
    elif tool_id == "custom_sqli_probe":
        for rec in records:
            if isinstance(rec, dict) and "vulnerable" in rec:
                rows.extend(_map_sqli_probe(rec))
    elif tool_id == "custom_wp_check":
        for rec in records:
            if isinstance(rec, dict):
                rows.extend(_map_wp_check(rec))
    elif tool_id == "custom_php_check":
        for rec in records:
            if isinstance(rec, dict):
                rows.extend(_map_php_check(rec))
    elif tool_id == "custom_php2shell":
        for rec in records:
            if isinstance(rec, dict):
                rows.extend(_map_php2shell(rec))
    else:
        # tool lain (recon) tidak menghasilkan vulnerability finding
        return []

    if not rows:
        return []

    # bukti: file JSON hasil mapping per tool
    out_dir = settings.EVIDENCE_DIR / run_id
    out_dir.mkdir(parents=True, exist_ok=True)
    ev_path = out_dir / f"{tool_id}_findings.json"
    ev_path.write_text(json.dumps(rows, indent=2, default=str), encoding="utf-8")

    run = db.query_one("SELECT target_id FROM workflow_runs WHERE id=%s", (run_id,))
    target_id = run["target_id"] if run else None

    existing = {
        r["title"] for r in db.query(
            "SELECT title FROM findings WHERE run_id=%s AND source_tool=%s",
            (run_id, tool_id))
    }

    ids: list[int] = []
    for f in rows:
        if f["title"] in existing:
            continue
        ids.append(db.insert_returning_id(
            "INSERT INTO findings (run_id, asset_id, severity, title, cvss_score, cwe, cve, "
            "summary, evidence_path, impact, remediation, `references`, source_tool) "
            "VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)",
            (run_id, target_id, f["severity"], f["title"], f["cvss_score"], f["cwe"],
             f["cve"], f["summary"], str(ev_path), f["impact"], f["remediation"],
             json.dumps(f["references"]), f["source_tool"]),
        ))
        existing.add(f["title"])
    return ids


# ------------------------------------------------------------- enrichment ----

def enrich_findings(run_id: str, ids: list[int]) -> int:
    """AI memperkaya field finding + validasi ulang severity (guardrail Bagian 6).

    1 panggilan per batch (hemat rate limit). Kalau AI gagal → finding tetap
    tersimpan dengan data mentah (fallback, tidak memblokir workflow).
    Return jumlah finding yang berhasil diperkaya.
    """
    if not ids:
        return 0
    rows = db.query(
        "SELECT id, severity, title, cvss_score, cwe, cve, summary, source_tool "
        "FROM findings WHERE run_id=%s AND id IN (" + ",".join(["%s"] * len(ids)) + ")",
        (run_id, *ids),
    )
    if not rows:
        return 0

    payload = json.dumps(rows, default=str)
    try:
        raw = client.chat_simple(
            "Kamus analis keamanan senior. Untuk TIAP finding berikut, tulis ringkas:\n"
            "- summary (1-2 kalimat, bahasa Indonesia, jelaskan akar masalah)\n"
            "- impact (1 kalimat dampak nyata kalau dieksploitasi)\n"
            "- remediation (1-2 kalimat cara fix konkret)\n"
            "- severity ulang: nilai ulang dari critical/high/medium/low/info berdasarkan "
            "konteks (tool kadang over/under-report — CVE/CVSS bantu validasi)\n\n"
            'Balas HANYA JSON array valid: [{"id":..,"severity":"..","summary":"..",'
            '"impact":"..","remediation":".."}] urutan sama seperti input. Tanpa teks lain.',
            f"Findings run {run_id}:\n{payload[:6000]}",
            max_tokens=1800,
        )
        data = _parse_json_array(raw)
    except AIError:
        return 0

    if not data:
        return 0

    valid_sev = set(_SEV_MAP.values())
    enriched = 0
    for item in data:
        if not isinstance(item, dict) or "id" not in item:
            continue
        fid = int(item["id"])
        if fid not in ids:
            continue
        sev = _norm_sev(item.get("severity"))
        if sev not in valid_sev:
            sev = "info"
        db.execute(
            "UPDATE findings SET severity=%s, summary=%s, impact=%s, remediation=%s "
            "WHERE id=%s AND run_id=%s",
            (sev,
             str(item.get("summary") or "")[:2000] or None,
             str(item.get("impact") or "")[:2000] or None,
             str(item.get("remediation") or "")[:2000] or None,
             fid, run_id),
        )
        enriched += 1
    return enriched


def _parse_json_array(text: str) -> list | None:
    """Parse JSON array dari respons AI (toleran code-fence/teks sisipan)."""
    t = text.strip()
    t = re.sub(r"^```(?:json)?\s*|\s*```$", "", t)
    start, end = t.find("["), t.rfind("]")
    if start == -1 or end == -1 or end <= start:
        return None
    try:
        data = json.loads(t[start:end + 1])
        return data if isinstance(data, list) else None
    except json.JSONDecodeError:
        return None


# --------------------------------------------------------- overall summary ----

def generate_overall_summary(run_id: str) -> str:
    """1 panggilan GLM di akhir workflow: agregat semua findings + rekomendasi.

    Dipakai reports.overall_summary + report.pdf (Bagian 6 PROMPT.md).
    """
    run = db.query_one(
        "SELECT r.id, r.status, r.started_at, r.finished_at, t.value AS target, "
        "wt.name AS template_name FROM workflow_runs r "
        "JOIN targets t ON t.id = r.target_id "
        "JOIN workflow_templates wt ON wt.id = r.template_id WHERE r.id = %s",
        (run_id,))
    if run is None:
        return "(run tidak ditemukan)"
    findings = db.query(
        "SELECT severity, title, cvss_score, cwe, source_tool FROM findings "
        "WHERE run_id = %s ORDER BY FIELD(severity,'critical','high','medium','low','info'), "
        "id", (run_id,))
    tools_used = [r["source_tool"] for r in db.query(
        "SELECT DISTINCT source_tool FROM findings WHERE run_id=%s", (run_id,))]

    sev_counts = {s: 0 for s in ("critical", "high", "medium", "low", "info")}
    for f in findings:
        sev_counts[f["severity"]] = sev_counts.get(f["severity"], 0) + 1

    titles = "\n".join(
        f"- [{f['severity']}] {f['title']} (CVSS: {f['cvss_score'] or 'n/a'}, "
        f"{f['cwe'] or 'n/a'})" for f in findings[:40])

    prompt = (
        f"Data engagement penetration testing:\n"
        f"- Run ID: {run_id}\n- Target scope: {run['target'] if run else '?'}\n"
        f"- Workflow: {run['template_name'] if run else '?'}\n"
        f"- Durasi: {str(run['started_at'] or '?')[:19]} → {str(run['finished_at'] or '?')[:19]}\n"
        f"- Tools yang menghasilkan temuan: {', '.join(tools_used) or 'tidak ada'}\n"
        f"- Jumlah temuan: {len(findings)} "
        f"(critical: {sev_counts['critical']}, high: {sev_counts['high']}, "
        f"medium: {sev_counts['medium']}, low: {sev_counts['low']}, "
        f"info: {sev_counts['info']})\n\n"
        f"Daftar temuan (maks 40):\n{titles or '(tidak ada temuan — target bersih)'}\n\n"
        "Tulis ringkasan eksekutif bahasa Indonesia untuk manajemen (3-6 paragraf, "
        "pisahkan paragraf dengan baris kosong): kondisi keamanan target overall, "
        "pola temuan utama, risiko terbesar. Lalu 3 baris terakhir: 'Rekomendasi prioritas:' "
        "diikuti 3 rekomendasi urut dampak. Fakta dari data saja."
    )
    try:
        return client.chat_simple(
            "Kamu konsultan keamanan senior menulis ringkasan eksekutif laporan pentest. "
            "Objektif, berbasis data, tanpa dramatisasi.",
            prompt, max_tokens=1200,
        )
    except AIError as e:
        return (
            "Overall summary AI tidak tersedia saat run ini selesai "
            f"(fallback statistik). Temuan: {len(findings)} — " +
            ", ".join(f"{s}: {n}" for s, n in sev_counts.items() if n) +
            f". Error: {e}"
        )


def write_findings_event(run_id: str, phase: str, tool_id: str, ids: list[int]) -> None:
    write_event(run_id, phase, "finding_recorded", {
        "tool": tool_id, "count": len(ids),
        "message": f"{len(ids)} finding tercatat dari {tool_id}",
    })
