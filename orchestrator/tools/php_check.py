#!/usr/bin/env python3
"""php_check.py — tool custom OGES-SHIELD (Phase 6).

Deteksi versi PHP dari header X-Powered-By / generator meta secara benign,
lalu match ke daftar CVE PHP yang diketahui (offline, kecil).
Output JSON ke stdout — dipakai orchestrator + findings mapper.
Chain: httpx (tech-detect) → AI lihat PHP → pilih php_check → AI lihat CVE
→ pilih php2shell utk validasi exploit benign.
"""
from __future__ import annotations

import argparse
import json
import re
import sys
import urllib.request

UA = "oges-shield-php-check/0.1"
TIMEOUT = 15

PHP_CVE: dict[str, list[dict]] = {
    "8.2.0": [{"cve": "CVE-2023-0662", "sev": "high", "title": "SoapClient type confusion → RCE (POC21)",
               "desc": "Type confusion di ext-soap memberi crash/execute arbitrer."}],
    "8.1.0": [{"cve": "CVE-2021-21707", "sev": "medium", "title": "Filter chain → LFI to RCE (POC31)",
               "desc": "PHP filter chain dengan iconv dapat mengubah file read jadi eksekusi."}],
    "7.4.33": [{"cve": "CVE-2022-31625", "sev": "high", "title": "pg_query escape → SQLi (POC14)",
                "desc": "Pesan error pg_query expose konfigurasi, kombinasi injection."}],
    "7.3.33": [{"cve": "CVE-2021-21703", "sev": "medium", "title": "User enumeration via FPM (POC08)",
                "desc": "PHP-FPM mengekspos status user."}],
    "7.2.34": [{"cve": "CVE-2020-7071", "sev": "high", "title": "imap_mail() bypass → RCE (POC24)",
                "desc": "imap_mail bypass escape command."}],
    "5.6.40": [{"cve": "CVE-2019-11043", "sev": "critical", "title": "PHP-FPM underflow → RCE (POC11)",
                "desc": "Buffer underflow di php-fpm dapat eksekusi kode remote tanpa auth (exploit publik CVE-2019-11043)."}],
}


def _headers(url: str) -> tuple[int, dict] | None:
    try:
        req = urllib.request.Request(url, headers={"User-Agent": UA})
        with urllib.request.urlopen(req, timeout=TIMEOUT) as r:
            return r.status, dict(r.headers)
    except Exception:
        return None


def detect(target_url: str) -> dict:
    out: dict = {
        "target": target_url,
        "is_php": False,
        "php_version": None,
        "server": None,
        "cves": [],
        "notes": [],
    }
    r = _headers(target_url)
    if not r:
        out["notes"].append("target tidak merespons")
        return out
    _, hdrs = r
    out["server"] = (hdrs.get("Server") or hdrs.get("server") or "")[:120]
    powered = (hdrs.get("X-Powered-By") or hdrs.get("x-powered-by") or "")

    if "php" in powered.lower():
        m = re.search(r"PHP[/\s]*([\d.]+)", powered, re.I)
        out["is_php"] = True
        out["php_version"] = m.group(1) if m else "unknown"
        out["notes"].append(f"x-powered-by: {powered[:120]}")
    else:
        out["notes"].append("header X-Powered-By tidak menyebut PHP — tebak dari tool httpx tech-detect lebih akurat")
        return out

    ver = out.get("php_version") or ""
    cves = PHP_CVE.get(ver, [])
    out["cves"] = cves
    if ver and ver != "unknown" and not cves:
        out["notes"].append(f"versi PHP {ver} tidak ada di DB CVE offline (bukan berarti aman)")
    return out


def main() -> None:
    ap = argparse.ArgumentParser(description="PHP version + CVE check (benign)")
    ap.add_argument("--url", required=True, help="URL target")
    args = ap.parse_args()
    print(json.dumps(detect(args.url)))


if __name__ == "__main__":
    sys.exit(main())
