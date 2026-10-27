#!/usr/bin/env python3
"""mock_wp.py — target demo WordPress + PHP vulnerable (LOCAL, untuk E2E chain).

Server HTTP kecil meniru situs WordPress 5.6.40 + PHP 5.6.40 (X-Powered-By)
dengan endpoint yang POC php2shell tandai valid. HANYA utk demo lokal —
didaftarkan sebagai target in-scope di platform. Port 127.0.0.1:8899.
"""
import http.server
import socketserver
import re

PORT = 8899
WP_VER = "5.6.40"
PHP_VER = "5.6.40"

PAGE = f"""<!DOCTYPE html><html><head>
<meta name="generator" content="WordPress {WP_VER}">
<link rel="stylesheet" href="/wp-content/themes/twentytwentyone/style.css">
</head><body><h1>MockWP demo</h1><p>demo target OGES-SHIELD</p></body></html>"""


class H(http.server.BaseHTTPRequestHandler):
    def _send(self, code: int, body: bytes = b"", ctype: str = "text/html"):
        self.send_response(code)
        self.send_header("Content-Type", ctype)
        self.send_header("X-Powered-By", f"PHP/{PHP_VER}")
        self.send_header("Server", "Apache/2.4.41")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        p = self.path
        if p == "/" or p.startswith("/?"):
            self._send(200, PAGE.encode())
        elif p.startswith("/wp-links-opml.php"):
            body = (f'<?xml version="1.0"?><opml version="1.0"><head>'
                    f'<title>Links</title></head><body>'
                    f'generator="WordPress {WP_VER}"'
                    f'</body></opml>').encode()
            self._send(200, body, "text/xml")
        elif p == "/readme.html":
            body = f"<h1>WordPress</h1><p>Version {WP_VER}</p>".encode()
            self._send(200, body)
        elif p == "/wp-content/uploads/":
            body = b"<ul><li>2024/</li><li>demo.zip</li></ul>"
            self._send(200, body)
        elif p.startswith("/index.php?"):
            # filter chain / fpm payload → 200 + isi (indikasi diterima)
            self._send(200, b"<html><body>mock response data 41414142454646</body></html>")
        else:
            self._send(404, b"<html><body>404 not found</body></html>")

    def log_message(self, format, *args):  # senyap
        pass


if __name__ == "__main__":
    with socketserver.TCPServer(("127.0.0.1", PORT), H) as httpd:
        print(f"mock WP {WP_VER} + PHP {PHP_VER} di http://127.0.0.1:{PORT}/")
        httpd.serve_forever()
