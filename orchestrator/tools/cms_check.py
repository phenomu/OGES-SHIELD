#!/usr/bin/env python3
"""cms_check.py — CMS checker OGES-SHIELD (Phase 6).

Baca daftar host dari input_file (hasil httpx live hosts / subfinder) ATAU
--url tunggal. Deteksi CMS/stack tiap host secara benign:
  - WordPress: wp-links-opml.php / readme.html / meta generator
  - PHP: header X-Powered-By
  - Laravel: cookie XSRF-TOKEN / X-Laravel? header laravel_session
  - cPanel: redirect /login?service=cpanel (port 2083/2082) atau header
  - Mailer/webmail: halaman roundcube/rainloop marker
  - static/no-cms: fallback
Output: JSON per host → stdout (jsonlines diparse orchestrator).
"""
from __future__ import annotations

import argparse
import json
import re
import sys
import urllib.request
import urllib.error

UA = "oges-shield-cms-check/0.1"
TIMEOUT = 12


def _get(url: str) -> tuple[int, str, dict] | None:
    try:
        req = urllib.request.Request(url, headers={"User-Agent": UA})
        with urllib.request.urlopen(req, timeout=TIMEOUT) as r:
            return r.status, r.read(120_000).decode("utf-8", "replace"), dict(r.headers)
    except urllib.error.HTTPError as e:
        body = e.read(120_000).decode("utf-8", "replace") if e.fp else ""
        return e.code, body, dict(e.headers or {})
    except Exception:
        return None


def parse_hosts_file(path: str) -> list[str]:
    hosts = []
    try:
        with open(path, encoding="utf-8", errors="replace") as fh:
            for ln in fh:
                ln = ln.strip()
                if not ln or ln.startswith("#"):
                    continue
                hosts.append(ln)
    except OSError:
        pass
    return hosts


def host_of(line: str) -> tuple[str, str]:
    """Baris 'host tech=x url=y' → (host, url)."""
    host = line.split()[0] if line.split() else ""
    m = re.search(r"url=(\S+)", line)
    url = m.group(1) if m else f"http://{host}"
    return host, url


def detect_one(host: str, url: str) -> dict:
    out = {
        "host": host, "url": url,
        "cms": None, "cms_version": None,
        "php_version": None, "framework": None,
        "nuclei_templates": [],
        "notes": [],
    }
    r = _get(url)
    if not r:
        out["notes"].append("host tidak merespons")
        return out
    code, body, hdrs = r
    powered = (hdrs.get("X-Powered-By") or hdrs.get("x-powered-by") or "")
    server = (hdrs.get("Server") or hdrs.get("server") or "")

    # PHP
    if "php" in powered.lower():
        m = re.search(r"PHP[/\s]*([\d.]+)", powered, re.I)
        out["php_version"] = m.group(1) if m else "unknown"

    # WordPress
    wp = None
    if re.search(r"wp-links-opml\.php", url) or "wp-links-opml" in body:
        pass
    r2 = _get(url.rstrip("/") + "/wp-links-opml.php?limit=1")
    wpver = None
    if r2 and r2[0] == 200:
        m = re.search(r"WordPress\s+([\d.]+)", r2[1])
        if m:
            wp = "WordPress"
            wpver = m.group(1)
    if not wp:
        m = re.search(r'content="WordPress\s+([\d.]+)"', body, re.I)
        if m:
            wp = "WordPress"
            wpver = m.group(1)
    if wp:
        out["cms"] = "WordPress"
        out["cms_version"] = wpver
        out["nuclei_templates"] = ["wordpress", "technologies/wordpress", "wordpress-plugins"]
        if out["php_version"] and out["php_version"] != "unknown":
            out["nuclei_templates"].append("php")
        out["notes"].append("deteksi via generator/OPML")

    # Laravel
    if not out["cms"] and ("laravel_session" in (hdrs.get("Set-Cookie") or "") or "XSRF-TOKEN" in (hdrs.get("Set-Cookie") or "")):
        out["framework"] = "Laravel"
        out["nuclei_templates"] = ["laravel", "technologies/laravel", "php"]
        out["notes"].append("deteksi via cookie Laravel")

    # cPanel
    if "cpanel" in url.lower() or re.search(r"service=cpanel|cPanel", body[:5000], re.I):
        out["framework"] = "cPanel"
        out["nuclei_templates"] = ["cpanel", "exposed panels"]
        out["notes"].append("marker cPanel terdeteksi")
    else:
        mport = re.search(r":(\d+)", url)
        if mport and mport.group(1) in ("2082", "2083", "2086", "2087"):
            out["framework"] = "cPanel"
            out["nuclei_templates"] = ["cpanel", "exposed panels"]
            out["notes"].append(f"port cPanel {mport.group(1)}")

    # webmail (roundcube/rainloop)
    if re.search(r"roundcube|rainloop|squirrelmail", body[:5000], re.I):
        out["framework"] = "Webmail"
        out["nuclei_templates"] = ["roundcube", "rainloop", "exposed panels"]
        out["notes"].append("marker webmail terdeteksi")

    # fallback tech dari header
    if not out["cms"] and not out["framework"]:
        out["notes"].append(f"tanpa CMS/framework jelas — server={server[:80]} powered={powered[:60] or 'none'}")
        out["nuclei_templates"] = ["http/technologies", "http/exposures"]

    if out["php_version"]:
        out["notes"].append(f"php {out['php_version']}")
    return out


def main() -> None:
    ap = argparse.ArgumentParser(description="CMS checker multi-host (benign)")
    ap.add_argument("--url", help="URL tunggal utk cek")
    ap.add_argument("--input-file", help="file daftar host (per baris, hasil httpx/subfinder)")
    args = ap.parse_args()

    targets: list[tuple[str, str]] = []
    if args.url:
        targets.append((args.url.split("//")[-1].split("/")[0], args.url))
    elif args.input_file:
        for ln in parse_hosts_file(args.input_file):
            h, u = host_of(ln)
            if h:
                targets.append((h, u))
    if not targets:
        print(json.dumps({"error": "tidak ada target — berikan --url atau --input-file"}))
        sys.exit(1)

    for host, url in targets:
        rec = detect_one(host, url)
        print(json.dumps(rec))


if __name__ == "__main__":
    sys.exit(main())
