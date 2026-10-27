#!/usr/bin/env python3
"""template.py — TEMPLATE TOOL CUSTOM OGES-SHIELD (Phase 6).

SALIN FILE INI untuk bikin tool baru (mis. wp2shell.py, ojs_check.py, dsb).
Ini file referensi — juga terdaftar di tools.json sebagai `custom_template`
(enabled: false) supaya polanya kelihatan di UI Tool Modules.

════════════════════════════════════════════════════════════════════════
ANATOMI TOOL CUSTOM — 3 BAGIAN YANG KAMU PERLUIN:
════════════════════════════════════════════════════════════════════════

1) FILE INI (orchestrator/tools/nama_tool.py)
   - Param masuk via argparse (AI mengisi sesuai input_schema)
   - script_path TIDAK usah dipikirkan — orchestrator inject otomatis
   - Output: JSON per baris (jsonlines) ke STDOUT
   - WAJIB benign: tidak exec command, tidak write file ke target,
     tidak persistence — cek reachability/poc marker saja (guardrail 9)

2) REGISTRASI di orchestrator/config/tools.json
   - copy entry `custom_template` di bawah, ganti id/name/phase/deskripsi
   - field penting:
     * id: "custom_<nama>" — prefix custom_ biar jelas buatan sendiri
     * valid_phases: phase di mana AI boleh pilih tool ini
       (scope_validation, reconnaissance, service_enumeration,
        vulnerability_assessment, exploitation_validation,
        persistence_simulation, cleanup_verification, report_generation)
     * risk_level: "safe" | "intrusive" | "destructive"
       (destructive → wajib "requires_confirmation": true → user approve dulu)
     * command_template: placeholder {param} HARUS cocok input_schema
     * input_schema: param yang AI lihat & isi (script_path disembunyikan
       dari AI otomatis — jangan hapus properties-nya)
   - setelah edit: klik "Muat Ulang" di Tool Modules, atau
     curl -X POST http://127.0.0.1:8100/internal/reload-tools

3) FINDINGS (opsional) di orchestrator/findings.py
   - kalau output toolmu mau jadi baris findings: tambah branch di
     map_tool_result() → _map_<nama_tool>(rec) seperti contoh di bawah
   - kontrak record yang dikenali mapper bawaan:
     * "vulnerable": true                    → sqli-probe style finding
     * "cves": [ {cve,sev,title,desc}, ... ] → tiap item jadi finding
     * "exploit_path_valid": true            → finding CRITICAL
     * "is_<tech>": true + versi             → info finding versi terdeteksi
   - tanpa mapper → output tetap masuk event stream + AI context
     (AI tetap bisa baca & memutuskan tool berikutnya), cuma tidak
     masuk tabel findings/report otomatis

════════════════════════════════════════════════════════════════════════
SNIPPET tools.json (copy + sesuaikan):
════════════════════════════════════════════════════════════════════════

{
  "id": "custom_nama_tool",
  "name": "Nama Tool — deskripsi singkat",
  "category": "exploitation",
  "valid_phases": ["exploitation_validation"],
  "risk_level": "intrusive",
  "command_template": "python3 {script_path} --url {target_url}",
  "input_schema": {
    "type": "object",
    "properties": {
      "target_url": {"type": "string"},
      "script_path": {"type": "string", "description": "(diisi orchestrator, bukan AI)"}
    },
    "required": ["target_url", "script_path"]
  },
  "output_parser": "jsonlines",
  "timeout_seconds": 120,
  "enabled": true,
  "description": "Deskripsi untuk AI: KAPAN tool ini dipakai (mis. 'setelah cms_check mendeteksi WordPress versi < X'). AI baca ini."
}

LALU daftarkan script di runner.py _CUSTOM_TOOLS:
  "custom_nama_tool": "nama_tool.py",
(script_path inject — AI tidak pernah bisa inject path)

════════════════════════════════════════════════════════════════════════
CONTOH OUTPUT yang diparse orchestrator (1 JSON per baris, stdout):
════════════════════════════════════════════════════════════════════════

{"target": "http://example.com/", "detected": true, "marker": "poc-marker-echo", "cves": [{"cve": "CVE-XXXX-YYYY", "sev": "high", "title": "Contoh judul", "desc": "Deskripsi dampak."}]}
{"target": "http://example.com/", "exploit_path_valid": false, "evidence": "HTTP 404 — path tidak terjangkau"}

Contoh mapper findings (orchestrator/findings.py, dalam map_tool_result):
    elif tool_id == "custom_nama_tool":
        for rec in records:
            if isinstance(rec, dict):
                rows.extend(_map_nama_tool(rec))
"""
from __future__ import annotations

import argparse
import json
import sys
import urllib.request
import urllib.error

UA = "oges-shield-template/0.1"   # ganti nama toolmu di sini
TIMEOUT = 15


def _get(url: str) -> tuple[int, str] | None:
    """Fetch benign — GET saja, baca sebagian body."""
    try:
        req = urllib.request.Request(url, headers={"User-Agent": UA})
        with urllib.request.urlopen(req, timeout=TIMEOUT) as r:
            return r.status, r.read(120_000).decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        return e.code, e.read(120_000).decode("utf-8", "replace") if e.fp else ""
    except Exception:
        return None


def run_check(target_url: str) -> dict:
    """LOGIKA TOOL KAMU di sini.

    Pola: request benign → cari marker indikasi → susun record.
    Record bebas bentuknya; kalau mau jadi findings pakai kontrak
    di docstring atas (vulnerable / cves / exploit_path_valid / versi).
    """
    out: dict = {
        "target": target_url,
        "detected": False,
        "marker": None,
        "cves": [],
        "notes": [],
    }
    r = _get(target_url)
    if not r:
        out["notes"].append("target tidak merespons")
        return out
    status, body = r
    out["notes"].append(f"HTTP {status}, body {len(body)}B")

    # CONTOH deteksi marker — ganti dengan logika toolmu
    if "poc-marker-echo" in body:          # marker POC benign
        out["detected"] = True
        out["marker"] = "poc-marker-echo"
        out["notes"].append("marker POC ditemukan — path valid")
    return out


def main() -> None:
    ap = argparse.ArgumentParser(description="Template tool custom OGES-SHIELD (benign)")
    ap.add_argument("--url", required=True, help="URL target (in-scope)")  # param AI isi
    args = ap.parse_args()

    # 1 JSON per baris → stdout (jsonlines — orchestrator parse ini)
    print(json.dumps(run_check(args.url)))


if __name__ == "__main__":
    sys.exit(main())
