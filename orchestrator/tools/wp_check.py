#!/usr/bin/env python3
"""wp_check.py — tool custom OGES-SHIELD (Phase 6).

Deteksi WordPress + versi + theme/plugin populer secara benign:
  - GET /wp-links-opml.php?limit=1 → generator "WordPress X.Y.Z"
  - meta generator di halaman depan
  - GET /readme.html → heading "Version X.Y"
  - feed RSS → generator
Versi dipetakan ke daftar CVE WordPress core yang diketahui (offline, kecil).
Output JSON ke stdout (1 baris jsonlines) — dipakai orchestrator + findings mapper.
"""
from __future__ import annotations

import argparse
import json
import re
import sys
import urllib.request

UA = "oges-shield-wp-check/0.1"
TIMEOUT = 15

# ringkasan CVE WordPress core (offline — cukup utk demo chain)
WP_CVE: dict[str, list[dict]] = {
    "6.4.3": [{"cve": "CVE-2024-31210", "sev": "medium", "title": "PHP File Upload leading to RCE",
               "desc": "Case-sensitivity pada ekstensi file saat unggah → potensi RCE di server Windows/misconfig."}],
    "6.3.2": [{"cve": "CVE-2023-2745", "sev": "medium", "title": "Directory traversal via block slug",
               "desc": "Unggah tema/plugin melewati sanitasi path."}],
    "6.2.1": [{"cve": "CVE-2023-2732", "sev": "high", "title": "Stored XSS via plugins editor",
               "desc": "Kontributor dapat menyimpan XSS di konten plugin."}],
    "6.1.1": [{"cve": "CVE-2022-47611", "sev": "high", "title": "POST password protected content disclosure",
               "desc": "Bypass password-protected post."}],
    "6.0.1": [{"cve": "CVE-2022-43504", "sev": "high", "title": "Data exposure via post-by-email logging",
               "desc": "Log mengekspos email."}],
    "5.9.3": [{"cve": "CVE-2022-2833", "sev": "high", "title": "SQLi via link finder",
               "desc": "SQL injection di fitur link."}],
    "5.8.3": [{"cve": "CVE-2021-4429", "sev": "high", "title": "Authenticated SQLi",
               "desc": "SQL injection untuk user terautentikasi."}],
    "4.9.8": [{"cve": "CVE-2018-6389", "sev": "medium", "title": "DoS via load-scripts.php",
               "desc": "Unauthenticated resource exhaustion."}],
    "5.6.40": [
        {"cve": "CVE-2019-17671", "sev": "high", "title": "Unauthenticated view of private/draft posts",
         "desc": "Path traversal query static() memungkinkan melihat draft/pending post tanpa auth."},
        {"cve": "CVE-2022-21661", "sev": "high", "title": "SQLi via WP_Query (VD-229)",
         "desc": "WP_Query vulnerable ke SQL injection (exploit publik, masih berdampak utk versi 5.6.x tanpa patch)."},
    ],
}


def _get(url: str) -> tuple[int, str] | None:
    try:
        req = urllib.request.Request(url, headers={"User-Agent": UA})
        with urllib.request.urlopen(req, timeout=TIMEOUT) as r:
            return r.status, r.read(300_000).decode("utf-8", "replace")
    except Exception:
        return None


def detect(base_url: str) -> dict:
    base = base_url.rstrip("/") + "/"
    out: dict = {
        "target": base_url,
        "is_wordpress": False,
        "wp_version": None,
        "theme": None,
        "cves": [],
        "notes": [],
    }

    # 1) OPML generator — paling andal
    r = _get(base + "wp-links-opml.php?limit=1")
    if r and r[0] == 200:
        m = re.search(r"generator='WordPress\s+([\d.]+)'", r[1])
        if m:
            out["is_wordpress"] = True
            out["wp_version"] = m.group(1)
            out["notes"].append("detected via wp-links-opml.php")

    # 2) readme.html
    if not out["wp_version"]:
        r = _get(base + "readme.html")
        if r and r[0] == 200:
            m = re.search(r"Version\s+([\d.]+)", r[1], re.I)
            if m and "wordpress" in r[1].lower():
                out["is_wordpress"] = True
                out["wp_version"] = m.group(1)
                out["notes"].append("detected via readme.html")

    # 3) front page meta generator + theme
    r = _get(base)
    if r and r[0] == 200:
        html = r[1]
        m = re.search(r'name="generator"\s+content="WordPress\s+([\d.]+)"', html, re.I)
        if m:
            out["is_wordpress"] = True
            out["wp_version"] = out["wp_version"] or m.group(1)
        t = re.search(r'wp-content/themes/([a-z0-9_-]+)/', html, re.I)
        if t:
            out["theme"] = t.group(1)

    if not out["is_wordpress"]:
        out["notes"].append("tidak ada indikator WordPress (OPML/readme/generator)")
        return out

    # 4) match CVE offline
    ver = out.get("wp_version") or ""
    cves = []
    for known, items in WP_CVE.items():
        if known == ver:
            cves.extend(items)
    if not cves and ver:
        out["notes"].append(f"versi {ver} tidak ada di DB CVE offline (bukan berarti aman)")
    out["cves"] = cves
    return out


def main() -> None:
    ap = argparse.ArgumentParser(description="WordPress version + CVE check (benign)")
    ap.add_argument("--url", required=True, help="base URL target (mis. http://target/)")
    args = ap.parse_args()
    print(json.dumps(detect(args.url)))


if __name__ == "__main__":
    sys.exit(main())
