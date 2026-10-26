#!/usr/bin/env bash
# OGES-SHIELD — install binari Go ProjectDiscovery ke bin/ (dipanggil orchestrator Phase 3).
# Usage: bash scripts/setup.sh
set -euo pipefail

BIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/bin"
mkdir -p "$BIN_DIR"

# Tool list: id:repo — cukup tambah baris kalau perlu tool baru
TOOLS=(
  "subfinder:projectdiscovery/subfinder/v2"
  "httpx:projectdiscovery/httpx/cmd/httpx"
  "nuclei:projectdiscovery/nuclei/v3/cmd/nuclei"
  "naabu:projectdiscovery/naabu/v2/cmd/naabu"
)

echo "== OGES-SHIELD tools setup =="
echo "Install dir: $BIN_DIR"

for pair in "${TOOLS[@]}"; do
  name="${pair%%:*}"
  repo="${pair#*:}"
  if command -v "$BIN_DIR/$name" >/dev/null 2>&1 || [ -x "$BIN_DIR/$name" ]; then
    echo "[skip] $name sudah ada di $BIN_DIR"
    continue
  fi
  echo "[install] $name ($repo)"
  GOBIN="$BIN_DIR" go install -v "github.com/$repo@latest"
done

# Nuclei butuh templates — download ke path user default (~/.config/nuclei/templates)
if [ -x "$BIN_DIR/nuclei" ]; then
  echo "[nuclei] update templates..."
  "$BIN_DIR/nuclei" -update-templates 2>/dev/null || echo "[warn] nuclei template update dilewati (bisa manual nanti)"
fi

echo "== Selesai =="
ls -la "$BIN_DIR"
