#!/usr/bin/env python3
"""sqli_probe.py — tool custom OGES-SHIELD (Phase 3).

Validasi SQL injection pada parameter URL target secara benign:
hanya menguji perbedaan respons boolean-based (AND 1=1 vs AND 1=2),
tidak mengekstraksi data apa pun. Output JSON ke stdout.
"""
from __future__ import annotations

import argparse
import json
import sys
import urllib.parse
import urllib.request


def fetch(url: str, timeout: float = 15) -> tuple[int, int, str]:
    req = urllib.request.Request(url, headers={"User-Agent": "oges-shield-sqli-probe/0.1"})
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        body = resp.read(200_000).decode("utf-8", "replace")
        return resp.status, len(body), body


def probe(base_url: str) -> dict:
    parsed = urllib.parse.urlparse(base_url)
    if not parsed.query:
        return {"vulnerable": False, "reason": "URL tanpa query parameter — tidak ada parameter untuk diuji"}

    params = urllib.parse.parse_qs(parsed.query, keep_blank_values=True)
    results = []
    vulnerable = False
    for key in list(params.keys()):
        for pair_idx, orig_value in enumerate(params[key][:1]):  # uji nilai pertama saja
            def build(value: str) -> str:
                q = urllib.parse.parse_qsl(parsed.query, keep_blank_values=True)
                q = [(k, value if i == pair_idx and k == key else v) for i, (k, v) in enumerate(q)]
                return parsed._replace(query=urllib.parse.urlencode(q)).geturl()

            try:
                s_true, len_true, _ = fetch(build(orig_value + " AND 1=1"))
                s_false, len_false, _ = fetch(build(orig_value + " AND 1=2"))
            except Exception as e:  # network error → param dilewati
                results.append({"param": key, "status": "error", "detail": str(e)[:200]})
                continue

            if s_true == s_false and len_true != len_false and len_true > 0:
                vulnerable = True
                results.append({
                    "param": key, "status": "suspicious",
                    "len_true": len_true, "len_false": len_false,
                    "technique": "boolean-based blind (payload AND 1=1/1=2)",
                })
            else:
                results.append({"param": key, "status": "no_diff", "len_true": len_true, "len_false": len_false})

    return {
        "target": base_url,
        "vulnerable": vulnerable,
        "params_tested": len(results),
        "results": results,
    }


def main() -> None:
    ap = argparse.ArgumentParser(description="OGES-SHIELD benign SQLi probe")
    ap.add_argument("--url", required=True, help="URL target dengan query parameter")
    args = ap.parse_args()
    try:
        print(json.dumps(probe(args.url), indent=2))
    except Exception as e:
        print(json.dumps({"error": str(e), "vulnerable": False}))
        sys.exit(1)


if __name__ == "__main__":
    main()
